# Sign-in code email presentation

## Decision log

- 2026-10-26: User approved all defaults after the read-only Step A report: inline CID logo; sign-in code only; white body in light mode; UTC only.
- Reused the official sidebar PNG, resampled with transparency to 300 x 56 (displayed 150 x 28). No logo recreation or change to the original asset.
- Kept the separate OTP template rather than sharing a layout with new-device alerts.
- Matched the text instruction punctuation to the existing HTML sentence (period instead of colon). All words, contact details, dynamic expiry and existing footer content remain intact.
- Outbound mail uses CID; the local browser preview substitutes a local image URL because browsers do not resolve MIME CIDs. Laravel's Mailable::render() itself creates preview data URIs, which the preview route replaces. Sent messages have no data URI.

## Step A findings

- `app/Mail/OtpMail.php`: subject `Your Elevate sign-in code`, HTML `emails.otp`, explicit plain-text alternative `emails.otp-text`.
- `app/Jobs/SendOtpMail.php`: encrypted queue job, configured OTP mailer and challenge delivery checks. Unchanged.
- Original email: table layout, inline styles, 560px maximum width, Arial/Helvetica/sans-serif, white card, red text fallback, gray code box. No published `resources/views/vendor/mail` theme.
- Sidebar: `resources/views/layouts/app.blade.php` uses `public/assets/images/elevate-logo.png`, 1536 x 285, 202,283 bytes, transparent with white lettering and red A (main red #ED472E).
- App font: Segoe UI, Tahoma, Geneva, Verdana, sans-serif. Email now uses Segoe UI, Roboto, Helvetica, Arial, sans-serif, plus a system monospace stack for the code.
- Other custom email: `NewDeviceLoginMail`, `emails.new-device` and `emails.new-device-text`, separate templates with similar original styling; no shared Blade layout. No custom reset, verification or invitation emails found.
- Development configuration: local environment, APP_URL=http://localhost, SMTP Gmail port 587. No Mailpit/Mailtrap setup found. No listener on localhost ports 1025, 2525 or 8025 during checks. Existing OTP log delivery is opt-in and local-only.
- Existing regression suites: OtpSecurityTest and LoginFlowTest.

## Files changed

| File | Reason |
| --- | --- |
| app/Mail/OtpMail.php | Remove hosted-logo selection; Blade embeds the official local PNG. Subject, expiry calculation and text alternative remain intact. |
| resources/views/emails/otp.blade.php | Self-contained 600px table layout, palette, dark-mode and mobile media queries, hidden preheader and CID image. |
| resources/views/emails/otp-text.blade.php | Match instruction punctuation to HTML. |
| public/images/email/elevate-logo.png | Transparent 2x official logo derivative, 9,409 bytes. |
| routes/web.php | Local-only synthetic preview with no-store response. |
| tests/Feature/OtpMailPresentationTest.php | Actual array-transport multipart/CID checks, copy, HTML constraints, contrast and preview access tests. |
| tests/Browser/render-otp-mail.php | Generate synthetic HTML and multipart EML using array transport, without account lookup or external delivery. |
| tests/Browser/otp-mail.mjs | Reproducible 600px/375px light/dark browser screenshots, image/overflow/copy/color/error assertions. |
| docs/otp-mail-presentation.md | Findings, decisions, verification and rollback record. |

No authentication services/controllers, queue jobs, app styles, database records or other email templates were changed. Pre-existing unrelated workspace edits remain untouched.

## Preview

With APP_ENV=local, run `php artisan serve`, then open:

`http://127.0.0.1:8000/dev/mail/sign-in-code`

This shows only dummy code 123456 and a fixed synthetic UTC expiry. It sends nothing and returns 404 outside local (existing production HTTP-to-HTTPS middleware can redirect HTTP first).

For standalone artifacts:

```
php tests/Browser/render-otp-mail.php
node tests/Browser/otp-mail.mjs
```

The browser script uses installed Windows Edge by default. Set EMAIL_BROWSER_PATH to an installed Chromium executable on other systems. Artifacts are in `storage/framework/testing/otp-mail/`:

- preview.html and preview.eml (dummy data only)
- 600-light.png, 600-dark.png, 375-light.png, 375-dark.png
- full-suite.txt.gz (compressed full test run output)

Screenshots use 600px and 375px viewport widths; at 600px the card shrinks inside the required 24px outer padding. Card maximum width is 600px on wider screens.

## Verification

- Authentication regression: 31 tests passed, 261 assertions.
- Presentation: 3 tests passed, 72 assertions.
- PHP Pint: passed for modified/new standalone PHP files.
- Sent HTML: 5,323 bytes, below 30 KB. Single PNG: 9,409 bytes, below 20 KB.
- Actual array-transport MIME message contains multipart/alternative and multipart/related, text/plain, text/html and inline PNG. Subject and recipient asserted; no external email sent.
- HTML checks passed: no scripts, remote fonts/styles, remote image URLs, data URIs, code links, CSS variables or unstyled body elements. All layout tables have presentation roles. Only media queries are in the style block.
- Edge/Chromium: four previews passed; image loads, no horizontal overflow, expected light/dark body colors, double-click selection exactly 123456, no page errors. All four screenshots visually inspected.
- Inbox clients NOT checked: Gmail web/app, Outlook desktop/web, Apple Mail/iOS Mail, Android mail apps. Their auto-inversion, CID handling, image-blocked rendering and copy selection require real inbox/device checks. Browser emulation does not establish compatibility with these clients.
- Local SMTP delivery not checked: no local mail capture listener was available. Used array transport and saved the complete synthetic EML instead. No real member addresses were used.

Contrast output (all pass >=4.5:1):

```
#111111 on #FFFFFF: 18.88:1
#F2F2F2 on #1A1A1A: 15.55:1
#B8B8B8 on #1A1A1A: 8.77:1
#525252 on #FFFFFF: 7.81:1
#111111 on #F3F4F6: 17.16:1
#525252 on #F3F4F6: 7.10:1
#F2F2F2 on #242424: 13.87:1
#B8B8B8 on #0A0A0A: 9.98:1
#FFFFFF on #1A1A1A: 17.40:1
```

## Full-suite result

php artisan test --compact: **386 passed, 4 failed, 5,107 assertions**, 311.49 seconds. Failures outside email/authentication:

- MovementOverviewTest: sample opt-in/export restriction expected 403, received 500.
- SatisfactionTest: one-answer state expected Answers have been recorded, absent.
- SatisfactionTest: separate-day small groups expected the combined-result privacy explanation, absent.
- SystemFeedbackSentimentTest: admin feedback-form access expected 403, received 200.

The full-suite output is preserved in gzip format. A follow-up run of only these four cases reproduced all four failures (12 assertions, 1.45 seconds), without running the new email tests. The workspace drive also reported zero free space during verification; generated test output was compressed to recover space. No unrelated files were deleted or product behavior changed to address these failures.

## Rollback

No commit was created. Commit only the nine listed files together when ready; `git revert <that-commit>` then rolls back this change in one revert, without migrations or data changes. Do not include unrelated existing workspace edits. Generated preview artifacts can be removed independently.