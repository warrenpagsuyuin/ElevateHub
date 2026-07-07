<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class OtpLoginController extends Controller
{
    private const LOGIN_EMAIL_KEY = 'login.email';

    private const LOGIN_STEP_KEY = 'login.step';

    /**
     * @var array<int, string>
     */
    private const DISTRICT_ROLES = ['district', 'district_user', 'satellite', 'satellite_user'];

    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $step = $request->session()->get(self::LOGIN_STEP_KEY, 'email');
        $email = $request->session()->get(self::LOGIN_EMAIL_KEY, old('email'));

        return view('auth.login', [
            'step' => $step,
            'email' => $email,
        ]);
    }

    public function checkEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower($validated['email']);
        $user = User::where('email', $email)->first();

        if (! $user) {
            return back()
                ->withErrors(['email' => 'We could not find an account with that email.'])
                ->onlyInput('email');
        }

        $request->session()->put(self::LOGIN_EMAIL_KEY, $user->email);

        if ($this->isAdmin($user)) {
            $this->clearOtp($user);
            $request->session()->put(self::LOGIN_STEP_KEY, 'password');

            return redirect()
                ->route('login')
                ->with('status', 'Enter your admin password to continue.');
        }

        $this->sendOtp($user);
        $request->session()->put(self::LOGIN_STEP_KEY, 'otp');

        return redirect()
            ->route('login')
            ->with('status', $this->otpSentMessage());
    }

    public function loginWithPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $this->pendingUser($request);

        if (! $user || ! $this->isAdmin($user)) {
            $this->forgetLoginState($request);

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Password sign in is only available for admin accounts.']);
        }

        if (! Hash::check($validated['password'], (string) $user->password)) {
            return back()->withErrors(['password' => 'The password you entered is incorrect.']);
        }

        $this->clearOtp($user);
        Auth::login($user);
        $request->session()->regenerate();
        $this->forgetLoginState($request);

        return redirect()->route('admin.dashboard');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $this->pendingUser($request);

        if (! $user || $this->isAdmin($user)) {
            $this->forgetLoginState($request);

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'OTP sign in is not available for this account.']);
        }

        if (! $user->otp_code || ! $user->otp_expires_at || $user->otp_expires_at->isPast()) {
            $this->clearOtp($user);

            return back()->withErrors(['otp' => 'The code is invalid or has expired.']);
        }

        if (! Hash::check($validated['otp'], $user->otp_code)) {
            return back()->withErrors(['otp' => 'The code is invalid or has expired.']);
        }

        $this->clearOtp($user);
        Auth::login($user);
        $request->session()->regenerate();
        $this->forgetLoginState($request);

        return redirect($this->redirectPathFor($user));
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user || $this->isAdmin($user)) {
            $this->forgetLoginState($request);

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'OTP sign in is not available for this account.']);
        }

        $this->sendOtp($user);
        $request->session()->put(self::LOGIN_STEP_KEY, 'otp');

        return redirect()
            ->route('login')
            ->with('status', $this->otpSentMessage());
    }

    public function reset(Request $request): RedirectResponse
    {
        if ($user = $this->pendingUser($request)) {
            $this->clearOtp($user);
        }

        $this->forgetLoginState($request);

        return redirect()->route('login');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function pendingUser(Request $request): ?User
    {
        $email = $request->session()->get(self::LOGIN_EMAIL_KEY);

        if (! $email) {
            return null;
        }

        return User::where('email', $email)->first();
    }

    private function sendOtp(User $user): void
    {
        $otp = (string) random_int(100000, 999999);

        $user->forceFill([
            'otp_code' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(5),
        ])->save();

        try {
            Mail::to($user->email)->send(new OtpMail($otp));
        } catch (Throwable $exception) {
            $this->clearOtp($user);
            report($exception);

            throw ValidationException::withMessages([
                'email' => 'We could not send a one-time password right now. Please try again.',
            ]);
        }
    }

    private function clearOtp(User $user): void
    {
        $user->forceFill([
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();
    }

    private function forgetLoginState(Request $request): void
    {
        $request->session()->forget([
            self::LOGIN_EMAIL_KEY,
            self::LOGIN_STEP_KEY,
        ]);
    }

    private function isAdmin(User $user): bool
    {
        return $this->role($user) === 'admin';
    }

    private function redirectPathFor(User $user): string
    {
        if (in_array($this->role($user), self::DISTRICT_ROLES, true)) {
            return route('district.dashboard');
        }

        return route('home');
    }

    private function role(User $user): string
    {
        return str_replace([' ', '-'], '_', strtolower((string) ($user->role ?: 'member')));
    }

    private function otpSentMessage(): string
    {
        if (app()->environment('local') && config('mail.default') === 'log') {
            return 'We created your one-time password. Local email is using the log mailer, so the code is saved in storage/logs/laravel.log.';
        }

        return 'We sent a one-time password to your registered email.';
    }
}
