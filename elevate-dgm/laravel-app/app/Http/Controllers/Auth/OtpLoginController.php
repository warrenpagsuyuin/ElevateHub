<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NewDeviceNotifier;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;

class OtpLoginController extends Controller
{
    private const LOGIN_EMAIL_KEY = 'login.email';

    private const LOGIN_STEP_KEY = 'login.step';

    private const LOGIN_CHALLENGE_KEY = 'login.otp_challenge';

    public function __construct(private OtpService $otp, private NewDeviceNotifier $devices)
    {
    }

    public function show(Request $request): View
    {
        if (Auth::check()) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $step = $request->session()->get(self::LOGIN_STEP_KEY, 'email');
        $email = $request->session()->get(self::LOGIN_EMAIL_KEY, old('email'));
        $challengeId = $request->session()->get(self::LOGIN_CHALLENGE_KEY);

        // Sessions from before the OTP upgrade have no challenge: restart at the email step.
        if ($step === 'otp' && ! $challengeId) {
            $this->forgetLoginState($request);
            $step = 'email';
        }

        return view('auth.login', [
            'step' => $step,
            'email' => $email,
            'otpState' => $step === 'otp' ? $this->otp->state((int) $challengeId, (string) $email) : null,
        ]);
    }

    public function checkEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::where('email', $email)->first();

        // Admins keep the existing password step (accepted trade-off, see docs).
        if ($user && $this->isAdmin($user)) {
            $request->session()->put(self::LOGIN_EMAIL_KEY, $user->email);
            $request->session()->put(self::LOGIN_STEP_KEY, 'password');

            return redirect()->route('login');
        }

        // Unknown emails get an identical response (a decoy challenge that sends nothing).
        $challengeId = $this->otp->issue($email, $user, $request->ip());

        $request->session()->put(self::LOGIN_EMAIL_KEY, $email);
        $request->session()->put(self::LOGIN_STEP_KEY, 'otp');
        $request->session()->put(self::LOGIN_CHALLENGE_KEY, $challengeId);

        return redirect()->route('login')->with('status', $this->otpSentMessage());
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

        Auth::login($user);
        $request->session()->regenerate();
        $this->forgetLoginState($request);
        $this->devices->handle($user, $request);

        return redirect()->route('admin.dashboard');
    }

    public function verifyOtp(Request $request): RedirectResponse|JsonResponse
    {
        $challengeId = (int) $request->session()->get(self::LOGIN_CHALLENGE_KEY);

        if (! $challengeId) {
            return $this->restart($request, 'OTP sign in is not available for this account.');
        }

        $code = (string) $request->input('otp', '');

        if (! preg_match('/^\d{6}$/', $code)) {
            return $this->otpError($request, 'Enter all six digits to continue.');
        }

        $refusal = null;
        $result = $this->otp->verify($challengeId, $code, $request->ip(), function (User $user) use (&$refusal): bool {
            if ($this->isAdmin($user)) {
                $refusal = 'OTP sign in is not available for this account.';
            } elseif ($user->hasArchivedSatellite()) {
                // Checked only after a valid code so the email step reveals nothing.
                $refusal = 'This satellite is archived. Contact an administrator.';
            }

            if ($refusal) {
                return false;
            }

            Auth::login($user);

            return true;
        });

        if ($result['status'] === OtpService::RESULT_OK) {
            $user = $result['user'];
            $request->session()->regenerate();
            $this->forgetLoginState($request);
            $this->devices->handle($user, $request);
            $target = $this->redirectPathFor($user);

            return $request->expectsJson() ? response()->json(['redirect' => $target]) : redirect($target);
        }

        if ($result['status'] === OtpService::RESULT_REJECTED) {
            return $this->restart($request, (string) $refusal);
        }

        $state = $this->currentState($request);
        $message = match ($result['status']) {
            OtpService::RESULT_INVALID => sprintf(
                "That code isn't correct. %d %s left.",
                $state['attempts_left'],
                $state['attempts_left'] === 1 ? 'attempt' : 'attempts'
            ),
            OtpService::RESULT_LOCKED => 'Too many attempts. Request a new code.',
            default => 'Code expired. Request a new code.',
        };

        return $this->otpError($request, $message, $state);
    }

    public function resendOtp(Request $request): RedirectResponse|JsonResponse
    {
        $challengeId = (int) $request->session()->get(self::LOGIN_CHALLENGE_KEY);

        if (! $challengeId) {
            return $this->restart($request, 'Please enter your email again.');
        }

        $result = $this->otp->resend($challengeId, $request->ip());

        if ($result['status'] === OtpService::RESULT_REJECTED) {
            return $this->restart($request, 'Please enter your email again.');
        }

        [$message, $status] = match ($result['status']) {
            OtpService::RESEND_OK => ['A new code is on its way. Check your email.', 200],
            OtpService::RESEND_COOLDOWN => ['Please wait before requesting another code.', 429],
            default => ['Resend limit reached. Try again later or use another email.', 429],
        };

        if ($request->expectsJson()) {
            return response()->json(['message' => $message] + $this->currentState($request), $status);
        }

        return $status === 200
            ? redirect()->route('login')->with('status', $message)
            : redirect()->route('login')->withErrors(['otp' => $message]);
    }

    public function state(Request $request): JsonResponse
    {
        if (! $request->session()->get(self::LOGIN_CHALLENGE_KEY)) {
            return response()->json(['redirect' => route('login')]);
        }

        return response()->json($this->currentState($request));
    }

    public function reset(Request $request): RedirectResponse
    {
        if ($challengeId = $request->session()->get(self::LOGIN_CHALLENGE_KEY)) {
            $this->otp->cancel((int) $challengeId);
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

    /** @return array<string, mixed> */
    private function currentState(Request $request): array
    {
        return $this->otp->state(
            (int) $request->session()->get(self::LOGIN_CHALLENGE_KEY),
            (string) $request->session()->get(self::LOGIN_EMAIL_KEY, '')
        );
    }

    /** @param  array<string, mixed>|null  $state */
    private function otpError(Request $request, string $message, ?array $state = null): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message] + ($state ?? $this->currentState($request)), 422);
        }

        return redirect()->route('login')->withErrors(['otp' => $message]);
    }

    private function restart(Request $request, string $message): RedirectResponse|JsonResponse
    {
        $this->forgetLoginState($request);
        // Flash so the email step shows the reason after either a redirect or a JS navigation.
        $request->session()->flash('errors', (new ViewErrorBag)->put('default', new MessageBag(['email' => $message])));

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'redirect' => route('login')], 409)
            : redirect()->route('login');
    }

    private function pendingUser(Request $request): ?User
    {
        $email = $request->session()->get(self::LOGIN_EMAIL_KEY);

        return $email ? User::where('email', $email)->first() : null;
    }

    private function forgetLoginState(Request $request): void
    {
        $request->session()->forget([
            self::LOGIN_EMAIL_KEY,
            self::LOGIN_STEP_KEY,
            self::LOGIN_CHALLENGE_KEY,
        ]);
    }

    private function isAdmin(User $user): bool
    {
        return $this->role($user) === 'admin';
    }

    private function redirectPathFor(User $user): string
    {
        $role = $this->role($user);
        $satellite = $user->satelliteForDistrict();

        if ($satellite) {
            return route('satellites.dashboard', $satellite);
        }

        if (in_array($role, ['district', 'district_user', 'satellite', 'satellite_user'], true)) {
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
        if (app()->environment('local') && config('otp.allow_local_log') && config('otp.mailer') === 'log') {
            return 'Local testing: the code was written to storage/logs/laravel.log (OTP_ALLOW_LOCAL_LOG=true).';
        }

        // Identical for registered and unknown emails.
        return 'We sent a one-time password to your email.';
    }
}
