<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
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

    public function test_unknown_email_shows_requested_error(): void
    {
        $this->from('/')
            ->post(route('login.email'), ['email' => 'missing@example.com'])
            ->assertRedirect('/')
            ->assertSessionHasErrors([
                'email' => 'We could not find an account with that email.',
            ]);
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
        $member = User::factory()->create([
            'role' => 'member',
            'otp_code' => Hash::make('123456'),
            'otp_expires_at' => now()->subMinute(),
        ]);

        $this->withSession([
            'login.email' => $member->email,
            'login.step' => 'otp',
        ])->post(route('login.otp'), ['otp' => '123456'])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'otp' => 'The code is invalid or has expired.',
            ]);

        $this->assertGuest();
        $this->assertNull($member->refresh()->otp_code);
        $this->assertNull($member->otp_expires_at);
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
