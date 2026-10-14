<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | CCF Elevate</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #ffffff;
            color: #18181b;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .login-page {
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .login-shell {
            width: 100%;
            max-width: 420px;
        }

        .logo-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 18px;
        }

        .brand-logo {
            display: block;
            width: min(430px, 100%);
            height: 112px;
            object-fit: cover;
            object-position: center;
        }

        .login-card {
            border: 1px solid #e4e4e7;
            border-radius: 8px;
            background: #ffffff;
            padding: 32px;
            box-shadow: 0 18px 45px rgba(24, 24, 27, 0.08);
        }

        .login-copy {
            margin-bottom: 28px;
        }

        .login-title {
            margin: 0;
            color: #18181b;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: 0;
            line-height: 1.25;
        }

        .login-instruction {
            margin: 8px 0 0;
            color: #52525b;
            font-size: 14px;
            line-height: 1.6;
        }

        .notice,
        .error {
            margin: 0 0 20px;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 14px;
            line-height: 1.45;
        }

        .notice {
            border: 1px solid #e4e4e7;
            background: #fafafa;
            color: #3f3f46;
        }

        .error {
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #b91c1c;
        }

        .form {
            display: grid;
            gap: 20px;
        }

        .field-label {
            display: block;
            margin-bottom: 8px;
            color: #27272a;
            font-size: 14px;
            font-weight: 500;
        }

        .input {
            width: 100%;
            height: 48px;
            border: 1px solid #71717a;
            border-radius: 8px;
            background: #ffffff;
            color: #18181b;
            font-size: 16px;
            outline: none;
            padding: 0 16px;
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }

        .input:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.12);
        }

        .input::placeholder {
            color: #71717a;
            opacity: 1;
        }

        .input:focus-visible,
        .primary-button:focus-visible,
        .secondary-button:focus-visible {
            outline: 3px solid #18181b;
            outline-offset: 3px;
        }

        .error ul {
            margin: 0;
            padding-left: 20px;
            list-style: disc;
        }

        .otp-input {
            text-align: center;
            font-size: 18px;
            font-weight: 600;
        }

        .otp-digits {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 8px;
        }

        .otp-digits[hidden] {
            display: none;
        }

        .otp-digit {
            width: 100%;
            min-width: 0;
            height: 52px;
            border: 1px solid #71717a;
            border-radius: 8px;
            background: #ffffff;
            color: #18181b;
            padding: 0;
            text-align: center;
            font: 600 24px ui-monospace, monospace;
            font-variant-numeric: tabular-nums;
        }

        .otp-digit:focus {
            outline: 3px solid #b42318;
            outline-offset: 2px;
        }

        .otp-digit:disabled {
            background: #f4f4f5;
            color: #52525b;
        }

        .otp-timer,
        .otp-lock {
            margin: 0 0 16px;
            font-size: 14px;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        .otp-timer[data-state="warning"] {
            color: #854d0e;
        }

        .otp-timer[data-state="expired"],
        .otp-lock {
            color: #b42318;
        }

        .otp-recipient {
            margin: 0 0 16px;
            color: #52525b;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .otp-help {
            margin: 8px 0 0;
            color: #52525b;
            font-size: 13px;
        }

        .otp-feedback:empty {
            display: none;
        }

        .primary-button:disabled,
        .secondary-button:disabled {
            cursor: not-allowed;
            background: #e5e7eb;
            color: #4b5563;
        }

        .secondary-button.resend-emphasis {
            border: 1px solid #b42318;
            background: #fef2f2;
            color: #b42318;
        }

        .otp-spinner {
            display: inline-block;
            width: 15px;
            height: 15px;
            margin-right: 8px;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            vertical-align: middle;
            animation: otp-spin 0.7s linear infinite;
        }

        @keyframes otp-spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 360px) {
            .login-page {
                padding: 24px 12px;
            }

            .login-card {
                padding: 24px 12px;
            }

            .otp-digits {
                gap: 4px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .otp-spinner {
                animation: none;
            }
        }

        .primary-button,
        .secondary-button {
            border: 0;
            border-radius: 8px;
            cursor: pointer;
            font: inherit;
        }

        .primary-button {
            width: 100%;
            height: 48px;
            background: #dc2626;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            transition: background 150ms ease, box-shadow 150ms ease;
        }

        .primary-button:hover {
            background: #b91c1c;
        }

        .primary-button:focus {
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.16);
        }

        .switch-form {
            margin-top: 20px;
        }

        .resend-form {
            margin-top: 16px;
        }

        .secondary-button {
            width: 100%;
            min-height: 44px;
            background: transparent;
            color: #52525b;
            font-size: 14px;
            font-weight: 500;
            padding: 0;
            transition: color 150ms ease;
        }

        .secondary-button:hover {
            color: #b91c1c;
        }

        @media (max-width: 480px) {
            .login-page {
                align-items: flex-start;
                padding-top: 48px;
            }

            .login-card {
                padding: 28px 22px;
            }

            .brand-logo {
                width: min(340px, 100%);
                height: 88px;
            }
        }
        @include('partials.accessibility-styles')
        @include('partials.cs114-theme-styles')
    </style>
</head>
<body class="antialiased">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    @php
        $currentStep = $step ?? 'email';
        $accountEmail = old('email', $email ?? '');
    @endphp

    <main class="login-page" id="main-content" tabindex="-1">
        <section class="login-shell" aria-labelledby="login-title">
            <div class="logo-wrap">
                <img class="brand-logo" src="{{ asset('assets/images/elevate-logo-login.png') }}" alt="Elevate logo">
            </div>

            <div class="login-card">
                <div class="login-copy">
                    <h1 class="login-title" id="login-title">Sign in</h1>
                    <p class="login-instruction" id="login-instruction">
                        @if ($currentStep === 'password')
                            Enter your admin password to continue.
                        @elseif ($currentStep === 'otp')
                            Enter the six-digit one-time password sent to your email.
                        @else
                            Enter your email address to continue.
                        @endif
                    </p>
                </div>

                @if (session('status'))
                    <p class="notice" role="status">
                        {{ session('status') }}
                    </p>
                @endif

                @if ($errors->any())
                    <div class="error" id="login-errors" role="alert">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($currentStep === 'password')
                    <form method="POST" action="{{ route('login.password') }}" class="form">
                        @csrf

                        <div>
                            <label class="field-label" for="password">Password</label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                aria-describedby="login-instruction{{ $errors->any() ? ' login-errors' : '' }}"
                                required
                                autofocus
                                class="input"
                            >
                        </div>

                        <button
                            type="submit"
                            class="primary-button"
                        >
                            Sign in with password
                        </button>
                    </form>
                @elseif ($currentStep === 'otp')
                    <div
                        id="otp-panel"
                        data-state-url="{{ route('login.otp.state') }}"
                        data-state="{{ json_encode($otpState) }}"
                    >
                        <p class="otp-recipient">Sent to <strong>{{ $otpState['masked_email'] }}</strong></p>
                        @php
                            $initialRemaining = max(
                                0,
                                \Illuminate\Support\Carbon::parse($otpState['expires_at'])->getTimestamp()
                                    - \Illuminate\Support\Carbon::parse($otpState['server_now'])->getTimestamp()
                            );
                        @endphp
                        <p
                            class="otp-timer"
                            id="otp-timer"
                            data-state="{{ $initialRemaining === 0 ? 'expired' : ($initialRemaining <= 60 ? 'warning' : 'normal') }}"
                        >
                            {{ $initialRemaining ? sprintf('Code expires in %02d:%02d', intdiv($initialRemaining, 60), $initialRemaining % 60) : 'Code expired. Request a new code.' }}
                        </p>
                        <p class="otp-lock" id="otp-lock" hidden></p>
                        <p class="notice otp-feedback" id="otp-feedback" role="status" aria-live="polite"></p>
                        <span class="sr-only" id="otp-announcement" role="status" aria-live="polite"></span>

                        <form method="POST" action="{{ route('login.otp') }}" class="form" id="otp-form">
                            @csrf

                            <div>
                                <label class="field-label" for="otp" id="otp-label">One-time password</label>
                                <input
                                    id="otp"
                                    name="otp"
                                    type="text"
                                    inputmode="numeric"
                                    pattern="[0-9]{6}"
                                    minlength="6"
                                    maxlength="6"
                                    autocomplete="one-time-code"
                                    aria-invalid="{{ $errors->has('otp') ? 'true' : 'false' }}"
                                    aria-describedby="login-instruction{{ $errors->any() ? ' login-errors' : '' }}"
                                    required
                                    autofocus
                                    class="input otp-input"
                                >
                                <div class="otp-digits" id="otp-digits" role="group" aria-labelledby="otp-label" hidden>
                                    @for ($digit = 1; $digit <= 6; $digit++)
                                        <input
                                            class="otp-digit"
                                            type="text"
                                            inputmode="numeric"
                                            autocomplete="{{ $digit === 1 ? 'one-time-code' : 'off' }}"
                                            aria-label="One-time password, digit {{ $digit }} of 6"
                                            aria-describedby="otp-help otp-feedback"
                                            autocapitalize="off"
                                            spellcheck="false"
                                        >
                                    @endfor
                                </div>
                                <p class="otp-help" id="otp-help">Enter or paste your 6-digit code. It verifies automatically.</p>
                            </div>

                            <button type="submit" class="primary-button" id="otp-verify">
                                <span class="otp-spinner" hidden></span><span data-button-label>Verify code and sign in</span>
                            </button>
                        </form>

                        <form method="POST" action="{{ route('login.otp.resend') }}" class="resend-form" id="otp-resend-form">
                            @csrf
                            <button type="submit" class="secondary-button" id="otp-resend">Resend code</button>
                        </form>
                        <p class="otp-help" id="otp-resend-limit"></p>
                    </div>
                    <script src="{{ asset('js/otp-login.js') }}" defer></script>
                @else
                    <form method="POST" action="{{ route('login.email') }}" class="form">
                        @csrf

                        <div>
                            <label class="field-label" for="email">Email address</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ $accountEmail }}"
                                autocomplete="email"
                                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                aria-describedby="login-instruction{{ $errors->any() ? ' login-errors' : '' }}"
                                required
                                autofocus
                                class="input"
                                placeholder="name@example.com"
                            >
                        </div>

                        <button
                            type="submit"
                            class="primary-button"
                        >
                            Continue with email
                        </button>
                    </form>
                @endif

                @if ($currentStep !== 'email')
                    <form method="POST" action="{{ route('login.reset') }}" class="switch-form">
                        @csrf
                        <button type="submit" class="secondary-button">
                            Use another email
                        </button>
                    </form>
                @endif
            </div>
        </section>
    </main>
</body>
</html>
