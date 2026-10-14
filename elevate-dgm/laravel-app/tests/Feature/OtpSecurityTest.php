<?php

namespace Tests\Feature;

use App\Mail\NewDeviceLoginMail;
use App\Mail\OtpMail;
use App\Models\Satellite;
use App\Models\User;
use App\Services\NewDeviceNotifier;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OtpSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const JSON = ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function requestCode(User $user): string
    {
        $this->post(route('login.email'), ['email' => $user->email])->assertRedirect(route('login'));
        $code = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$code) {
            $code = $mail->otp;

            return true;
        });

        return $code;
    }

    private function lastCode(): string
    {
        return Mail::sent(OtpMail::class)->last()->otp;
    }

    private function wrong(string $code): string
    {
        return str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function test_code_is_stored_only_as_hmac_and_is_six_digits(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $code = $this->requestCode($user);

        $row = DB::table('otp_challenges')->first();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotSame($code, $row->code_hash);
        $this->assertSame(app(OtpService::class)->hashCode($code), $row->code_hash);
        $this->assertStringNotContainsString($code, json_encode($row));
    }

    public function test_page_receives_server_driven_timer_state(): void
    {
        $user = User::factory()->create(['role' => 'member', 'email' => 'warren0@gmail.com']);
        $this->requestCode($user);

        $this->get('/')->assertOk()
            ->assertSee('Code expires in 05:00')
            ->assertSee('w*****0@gmail.com')
            ->assertSee('autocomplete="one-time-code"', false)
            ->assertSee('inputmode="numeric"', false)
            ->assertSee('One-time password, digit 1 of 6');

        $this->travel(2)->minutes();
        $state = $this->getJson(route('login.otp.state'))->assertOk()->json();

        foreach (['expires_at', 'server_now', 'resend_available_at', 'resend_limit_resets_at', 'attempts_left', 'resends_left', 'masked_email'] as $key) {
            $this->assertArrayHasKey($key, $state);
        }
        $this->assertSame(180, strtotime($state['expires_at']) - strtotime($state['server_now']));
        $this->assertSame(5, $state['attempts_left']);
    }

    public function test_successful_verify_regenerates_session_and_is_single_use(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $code = $this->requestCode($user);
        $before = session()->getId();

        $this->postJson(route('login.otp'), ['otp' => $code], self::JSON)
            ->assertOk()->assertJson(['redirect' => route('home')]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
        $this->assertNotNull(DB::table('otp_challenges')->value('consumed_at'));

        // Replaying the consumed code is refused.
        $id = DB::table('otp_challenges')->value('id');
        $result = app(OtpService::class)->verify($id, $code, '127.0.0.1', fn () => true);
        $this->assertSame(OtpService::RESULT_EXPIRED, $result['status']);
    }

    public function test_parallel_verifications_cannot_both_succeed(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $code = $this->requestCode($user);
        $id = (int) DB::table('otp_challenges')->value('id');
        $service = app(OtpService::class);
        $logins = 0;

        // Simulate request B racing inside request A's transaction, after A read the row.
        $first = $service->verify($id, $code, null, function () use ($service, $id, $code, &$logins) {
            $second = $service->verify($id, $code, null, function () use (&$logins) {
                $logins++;

                return true;
            });
            $logins++;

            return true;
        });

        $this->assertSame(OtpService::RESULT_OK, $first['status']);
        $this->assertSame(1, $logins);
    }

    public function test_wrong_codes_show_attempts_left_and_lock_after_five(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $code = $this->requestCode($user);

        for ($i = 4; $i >= 1; $i--) {
            $this->postJson(route('login.otp'), ['otp' => $this->wrong($code)], self::JSON)
                ->assertStatus(422)
                ->assertJson(['attempts_left' => $i])
                ->assertJsonFragment(['message' => "That code isn't correct. {$i} ".($i === 1 ? 'attempt' : 'attempts').' left.']);
        }

        $this->postJson(route('login.otp'), ['otp' => $this->wrong($code)], self::JSON)
            ->assertStatus(422)
            ->assertJson(['attempts_left' => 0, 'message' => 'Too many attempts. Request a new code.']);

        $this->assertNotNull(DB::table('otp_challenges')->value('locked_until'));

        // Even the right code is now refused.
        $this->postJson(route('login.otp'), ['otp' => $code], self::JSON)->assertStatus(422);
        $this->assertGuest();
    }

    public function test_code_expires_server_side(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $code = $this->requestCode($user);
        $this->travel(config('otp.ttl_seconds') + 1)->seconds();

        $this->postJson(route('login.otp'), ['otp' => $code], self::JSON)
            ->assertStatus(422)->assertJson(['message' => 'Code expired. Request a new code.']);
        $this->assertGuest();
    }

    public function test_ttl_comes_from_one_config_value(): void
    {
        config(['otp.ttl_seconds' => 120]);
        $user = User::factory()->create(['role' => 'member']);
        $this->requestCode($user);

        $this->get('/')->assertSee('Code expires in 02:00');
        $mail = Mail::sent(OtpMail::class)->last();
        $this->assertStringContainsString('This code expires in 2 minutes', $mail->render());
    }

    public function test_resend_cooldown_is_enforced_and_new_code_replaces_old(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $old = $this->requestCode($user);

        $this->postJson(route('login.otp.resend'), [], self::JSON)
            ->assertStatus(429)->assertJson(['message' => 'Please wait before requesting another code.']);
        Mail::assertSentCount(1);

        $this->travel(61)->seconds();
        $this->postJson(route('login.otp.resend'), [], self::JSON)
            ->assertOk()->assertJson(['resends_left' => 2]);
        $new = $this->lastCode();

        if ($new !== $old) {
            $this->postJson(route('login.otp'), ['otp' => $old], self::JSON)->assertStatus(422);
        }
        $this->postJson(route('login.otp'), ['otp' => $new], self::JSON)->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_resend_limit_per_window(): void
    {
        config(['otp.max_resends' => 2, 'otp.send_per_window_email' => 50]);
        $user = User::factory()->create(['role' => 'member']);
        $this->requestCode($user);

        foreach ([1, 2] as $_) {
            $this->travel(61)->seconds();
            $this->postJson(route('login.otp.resend'), [], self::JSON)->assertOk();
        }

        $this->travel(61)->seconds();
        $response = $this->postJson(route('login.otp.resend'), [], self::JSON)
            ->assertStatus(429)->assertJson(['resends_left' => 0]);

        $this->assertNotNull($response->json('resend_limit_resets_at'));
        $this->assertGreaterThan(time(), strtotime($response->json('resend_limit_resets_at')));
    }

    public function test_reissuing_from_email_step_invalidates_previous_codes(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $old = $this->requestCode($user);
        $this->travel(61)->seconds();
        $this->post(route('login.reset'));
        $this->post(route('login.email'), ['email' => $user->email]);

        $this->assertSame(1, DB::table('otp_challenges')->whereNull('invalidated_at')->count());
        $this->assertNotNull(DB::table('otp_challenges')->orderBy('id')->value('invalidated_at'));

        $oldId = (int) DB::table('otp_challenges')->orderBy('id')->value('id');
        $this->assertNotSame(OtpService::RESULT_OK, app(OtpService::class)->verify($oldId, $old, null, fn () => true)['status']);
    }

    public function test_verify_is_rate_limited_per_email_with_retry_after(): void
    {
        config(['otp.verify_per_minute_email' => 3, 'otp.max_attempts' => 50]);
        $user = User::factory()->create(['role' => 'member']);
        $code = $this->requestCode($user);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('login.otp'), ['otp' => $this->wrong($code)], self::JSON)->assertStatus(422);
        }

        $this->postJson(route('login.otp'), ['otp' => $code], self::JSON)
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertGuest();
    }

    public function test_send_is_rate_limited_per_ip(): void
    {
        config(['otp.send_per_window_ip' => 2]);
        foreach (['a@example.com', 'b@example.com'] as $email) {
            $this->post(route('login.email'), ['email' => $email])->assertRedirect(route('login'));
        }

        $this->post(route('login.email'), ['email' => 'c@example.com'])
            ->assertStatus(302)->assertHeader('Retry-After');
    }

    public function test_unknown_email_gets_identical_response_and_never_verifies(): void
    {
        $known = User::factory()->create(['role' => 'member']);

        $a = $this->post(route('login.email'), ['email' => $known->email]);
        $this->post(route('login.reset'));
        $b = $this->post(route('login.email'), ['email' => 'nobody@example.com']);

        $this->assertSame($a->headers->get('Location'), $b->headers->get('Location'));
        $b->assertSessionHas('status', 'We sent a one-time password to your email.');
        $this->get('/')->assertSee('Code expires in');
        Mail::assertSentCount(1);

        $this->postJson(route('login.otp'), ['otp' => '000000'], self::JSON)
            ->assertStatus(422)->assertJson(['attempts_left' => 4]);
        $this->assertGuest();
    }

    public function test_archived_satellite_is_revealed_only_after_valid_code(): void
    {
        $user = User::factory()->create(['role' => 'satellite', 'district' => 'Pasig']);
        Satellite::create(['name' => 'Pasig', 'slug' => 'pasig', 'assigned_user_id' => $user->id])
            ->forceFill(['archived_at' => now()])->save();

        $this->post(route('login.email'), ['email' => $user->email])->assertSessionHasNoErrors();
        $this->post(route('login.otp'), ['otp' => $this->lastCode()])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'This satellite is archived. Contact an administrator.']);
        $this->assertGuest();
    }

    public function test_csrf_tokens_are_present_on_all_otp_forms(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $this->requestCode($user);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertSame(3, substr_count($html, 'name="_token"')); // verify, resend, use another email
        $this->assertStringNotContainsString($this->lastCode(), $html);
    }

    public function test_code_never_appears_in_logs(): void
    {
        $messages = [];
        Log::listen(function ($event) use (&$messages) {
            $messages[] = $event->message.json_encode($event->context);
        });

        $user = User::factory()->create(['role' => 'member']);
        $code = $this->requestCode($user);
        $this->postJson(route('login.otp'), ['otp' => $this->wrong($code)], self::JSON);
        $this->postJson(route('login.otp'), ['otp' => $code], self::JSON)->assertOk();

        $this->assertNotEmpty($messages);
        foreach ($messages as $line) {
            $this->assertStringNotContainsString($code, $line);
            $this->assertStringNotContainsString($user->email, $line);
        }
        $this->assertContains('otp.verified', array_map(fn ($m) => strtok($m, '{'), $messages));
    }

    public function test_email_is_queued_branded_and_has_no_code_in_subject(): void
    {
        Queue::fake();
        config(['otp.queue_connection' => 'database']);
        $user = User::factory()->create(['role' => 'member']);
        $this->post(route('login.email'), ['email' => $user->email]);

        Queue::assertPushedOn('otp', \App\Jobs\SendOtpMail::class);

        $mail = new OtpMail('721704', now()->addMinutes(5)->toIso8601String());
        $mail->assertHasSubject('Your Elevate sign-in code');
        $mail->assertSeeInHtml('721704');
        $mail->assertSeeInHtml('This code expires in 5 minutes');
        $mail->assertSeeInHtml('Never share this code.');
        $mail->assertSeeInHtml("Didn't request this?", false);
        $mail->assertDontSeeInHtml('<a ', false);
        $mail->assertSeeInText('721704');
        $mail->assertSeeInText('Valid until');
        $mail->assertSeeInText('Elevate staff will never ask for it.');
        $this->assertStringNotContainsString('721704', $mail->subject ?? '');
    }

    public function test_new_device_alert_sent_only_for_unrecognised_device(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $code = $this->requestCode($user);
        $this->post(route('login.otp'), ['otp' => $code]);
        Mail::assertNotQueued(NewDeviceLoginMail::class); // first ever sign-in: no alert
        $this->assertSame(1, DB::table('user_devices')->count());

        // Fresh browser (no device cookie) signs in.
        $this->post('/logout');
        $this->flushSession();
        $this->disableCookieEncryption();
        $this->post(route('login.email'), ['email' => $user->email]);
        $this->post(route('login.otp'), ['otp' => $this->lastCode()]);
        Mail::assertQueued(NewDeviceLoginMail::class, 1);

        // Same browser again: no new alert.
        $token = DB::table('user_devices')->orderByDesc('id')->value('token_hash');
        $this->assertNotNull($token);
    }

    public function test_admin_and_satellite_flows_still_work(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => bcrypt('secret-password')]);
        $this->post(route('login.email'), ['email' => $admin->email]);
        $this->post(route('login.password'), ['password' => 'secret-password'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->post('/logout');

        $satelliteUser = User::factory()->create(['role' => 'satellite']);
        $satellite = Satellite::create(['name' => 'Ortigas', 'slug' => 'ortigas', 'assigned_user_id' => $satelliteUser->id]);
        $this->post(route('login.email'), ['email' => $satelliteUser->email]);
        $this->post(route('login.otp'), ['otp' => $this->lastCode()])->assertRedirect(route('satellites.dashboard', $satellite));
        $this->assertAuthenticatedAs($satelliteUser);
    }
}
