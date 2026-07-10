<!doctype html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | CCF Elevate</title>
    <?php if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))): ?>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <?php endif; ?>
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
            margin-bottom: 28px;
        }

        .brand-logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #111827;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .brand-mark {
            display: inline-grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 8px;
            background: #dc2626;
            color: #ffffff;
            font-size: 20px;
            font-weight: 800;
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
            border: 1px solid #d4d4d8;
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

        .otp-input {
            text-align: center;
            font-size: 18px;
            font-weight: 600;
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
            outline: none;
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
                font-size: 18px;
            }

            .brand-mark {
                width: 34px;
                height: 34px;
            }
        }
    </style>
</head>
<body>
    <?php
        $currentStep = $step ?? 'email';
        $accountEmail = old('email', $email ?? '');
    ?>

    <main class="login-page">
        <section class="login-shell">
            <div class="logo-wrap">
                <div class="brand-logo" aria-label="ELEVATE">
                    <span class="brand-mark" aria-hidden="true">E</span>
                    <span>ELEVATE</span>
                </div>
            </div>

            <div class="login-card">
                <div class="login-copy">
                    <h1 class="login-title">Sign in</h1>
                    <p class="login-instruction">
                        <?php if($currentStep === 'password'): ?>
                            Enter your admin password to continue.
                        <?php elseif($currentStep === 'otp'): ?>
                            Enter the one-time password sent to your email.
                        <?php else: ?>
                            Enter your email address to continue.
                        <?php endif; ?>
                    </p>
                </div>

                <?php if(session('status')): ?>
                    <p class="notice">
                        <?php echo e(session('status')); ?>

                    </p>
                <?php endif; ?>

                <?php if($errors->any()): ?>
                    <div class="error">
                        <?php echo e($errors->first()); ?>

                    </div>
                <?php endif; ?>

                <?php if($currentStep === 'password'): ?>
                    <form method="POST" action="<?php echo e(route('login.password')); ?>" class="form">
                        <?php echo csrf_field(); ?>

                        <div>
                            <label class="field-label" for="password">Password</label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                required
                                autofocus
                                class="input"
                            >
                        </div>

                        <button
                            type="submit"
                            class="primary-button"
                        >
                            Continue
                        </button>
                    </form>
                <?php elseif($currentStep === 'otp'): ?>
                    <form method="POST" action="<?php echo e(route('login.otp')); ?>" class="form">
                        <?php echo csrf_field(); ?>

                        <div>
                            <label class="field-label" for="otp">One-time password</label>
                            <input
                                id="otp"
                                name="otp"
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="6"
                                autocomplete="one-time-code"
                                required
                                autofocus
                                class="input otp-input"
                            >
                        </div>

                        <button
                            type="submit"
                            class="primary-button"
                        >
                            Continue
                        </button>
                    </form>

                    <form method="POST" action="<?php echo e(route('login.otp.resend')); ?>" class="resend-form">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="secondary-button">
                            Resend code
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="<?php echo e(route('login.email')); ?>" class="form">
                        <?php echo csrf_field(); ?>

                        <div>
                            <label class="field-label" for="email">Email address</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="<?php echo e($accountEmail); ?>"
                                autocomplete="email"
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
                            Continue
                        </button>
                    </form>
                <?php endif; ?>

                <?php if($currentStep !== 'email'): ?>
                    <form method="POST" action="<?php echo e(route('login.reset')); ?>" class="switch-form">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="secondary-button">
                            Use another email
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
<?php /**PATH C:\Users\PLPASIG\ElevateHub\elevate-dgm\laravel-app\resources\views/auth/login.blade.php ENDPATH**/ ?>