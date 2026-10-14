<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\Satellite;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_initially_asks_for_email_only(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee('name="email"', false)
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="otp"', false);
    }

    public function test_opening_site_always_starts_at_login_page(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Sign in');

        $this->assertGuest();
    }

    public function test_unknown_email_gets_the_same_generic_response(): void
    {
        Mail::fake();

        $this->from('/')
            ->post(route('login.email'), ['email' => 'missing@example.com'])
            ->assertRedirect(route('login'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'We sent a one-time password to your email.');

        Mail::assertNothingSent();
    }

    public function test_admin_seeder_creates_the_default_admin_login(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'admin@elevate.local')->firstOrFail();

        $this->assertSame('Elevate Admin', $admin->name);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_admin_uses_password_and_redirects_to_admin_dashboard(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => Hash::make('secret-password'),
        ]);

        $this->post(route('login.email'), ['email' => $admin->email])
            ->assertRedirect(route('login'));

        Mail::assertNothingSent();

        $this->get('/')
            ->assertOk()
            ->assertSee('name="password"', false)
            ->assertDontSee('name="otp"', false);

        $this->post(route('login.password'), ['password' => 'secret-password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull($admin->refresh()->otp_code);
    }

    public function test_district_user_uses_otp_and_redirects_to_district_dashboard(): void
    {
        Mail::fake();

        $districtUser = User::factory()->create([
            'role' => 'district',
        ]);

        $this->post(route('login.email'), ['email' => $districtUser->email])
            ->assertRedirect(route('login'));

        $sentOtp = null;

        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$sentOtp): bool {
            $sentOtp = $mail->otp;

            return strlen($sentOtp) === 6;
        });

        $this->get('/')
            ->assertOk()
            ->assertSee('name="otp"', false)
            ->assertDontSee('name="password"', false);

        $this->post(route('login.otp'), ['otp' => $sentOtp])
            ->assertRedirect(route('district.dashboard'));

        $this->assertAuthenticatedAs($districtUser);
        $this->assertNull($districtUser->refresh()->otp_code);
        $this->assertNull($districtUser->otp_expires_at);
    }

    public function test_assigned_satellite_user_uses_otp_and_redirects_to_satellite_dashboard(): void
    {
        Mail::fake();

        $satelliteUser = User::factory()->create([
            'role' => 'satellite',
        ]);
        $satellite = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'assigned_user_id' => $satelliteUser->id,
        ]);

        $this->post(route('login.email'), ['email' => $satelliteUser->email])
            ->assertRedirect(route('login'));

        $sentOtp = null;

        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$sentOtp): bool {
            $sentOtp = $mail->otp;

            return strlen($sentOtp) === 6;
        });

        $this->post(route('login.otp'), ['otp' => $sentOtp])
            ->assertRedirect(route('satellites.dashboard', $satellite));

        $this->assertAuthenticatedAs($satelliteUser);
    }

    public function test_assigned_staff_user_is_redirected_to_their_satellite_dashboard_after_otp_login(): void
    {
        Mail::fake();

        $staffUser = User::factory()->create([
            'role' => 'staff',
        ]);
        $satellite = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'assigned_user_id' => $staffUser->id,
        ]);

        $this->post(route('login.email'), ['email' => $staffUser->email])
            ->assertRedirect(route('login'));

        $sentOtp = null;

        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$sentOtp): bool {
            $sentOtp = $mail->otp;

            return strlen($sentOtp) === 6;
        });

        $this->post(route('login.otp'), ['otp' => $sentOtp])
            ->assertRedirect(route('satellites.dashboard', $satellite));

        $this->assertAuthenticatedAs($staffUser);
    }

    public function test_member_uses_otp_and_redirects_to_homepage(): void
    {
        Mail::fake();

        $member = User::factory()->create([
            'role' => 'member',
        ]);

        $this->post(route('login.email'), ['email' => $member->email])
            ->assertRedirect(route('login'));

        $sentOtp = null;

        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$sentOtp): bool {
            $sentOtp = $mail->otp;

            return strlen($sentOtp) === 6;
        });

        $this->post(route('login.otp'), ['otp' => $sentOtp])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($member);
        $this->assertNull($member->refresh()->otp_code);
    }

    public function test_invalid_or_expired_otp_is_rejected(): void
    {
        Mail::fake();
        $member = User::factory()->create(['role' => 'member']);

        $this->post(route('login.email'), ['email' => $member->email]);
        $this->travel(301)->seconds();

        $this->post(route('login.otp'), ['otp' => '123456'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['otp' => 'Code expired. Request a new code.']);

        $this->assertGuest();
    }

    public function test_legacy_otp_session_without_a_challenge_restarts_at_email(): void
    {
        $member = User::factory()->create([
            'role' => 'member',
            'otp_code' => Hash::make('123456'),
            'otp_expires_at' => now()->addMinute(),
        ]);

        $this->withSession(['login.email' => $member->email, 'login.step' => 'otp'])
            ->post(route('login.otp'), ['otp' => '123456'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_roles_cannot_use_the_wrong_login_method(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member']);

        $this->withSession([
            'login.email' => $admin->email,
            'login.step' => 'otp',
        ])->post(route('login.otp'), ['otp' => '123456'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->withSession([
            'login.email' => $member->email,
            'login.step' => 'password',
        ])->post(route('login.password'), ['password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
