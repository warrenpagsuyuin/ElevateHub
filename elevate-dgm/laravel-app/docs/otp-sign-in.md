# Elevate OTP sign-in deployment

## Production configuration

Use a domain controlled by the organization. The application ships with safe placeholders; replace them before enabling production delivery.

```dotenv
APP_URL=https://app.your-domain.example
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.your-provider.example
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=provider-issued-username
MAIL_PASSWORD=provider-issued-secret
MAIL_FROM_ADDRESS=no-reply@your-domain.example
MAIL_FROM_NAME="CCF Elevate"
OTP_MAILER=smtp
OTP_QUEUE_CONNECTION=database
OTP_QUEUE=otp
OTP_PEPPER=a-separate-high-entropy-secret
OTP_LOGO_URL=https://app.your-domain.example/assets/images/elevate-logo-login.png
```

Do not commit the mail password or OTP pepper. Store both in the production secret manager. The logo URL must use HTTPS; otherwise the email uses the text wordmark.

Start a supervised worker after deploying the migration:

```sh
php artisan migrate --force
php artisan queue:work --queue=otp,default --tries=3
```

The OTP job makes up to three attempts, with 10- and 30-second retry delays. Its queue payload is encrypted. Failed sends are recorded on the challenge without recording the OTP.

## DNS checklist

The exact SPF and DKIM values come from the chosen provider's console. Do not substitute example values for provider-issued records.

| Record | Host | Value / action |
| --- | --- | --- |
| SPF TXT | `@` | Publish the provider's SPF include, such as `v=spf1 include:provider.example -all`. Keep one SPF record only; merge it with any existing sender's include. |
| DKIM CNAME/TXT | Provider selector, for example `selector1._domainkey` | Publish every DKIM record supplied by the provider, then enable DKIM in its console. |
| DMARC TXT | `_dmarc` | Start with `v=DMARC1; p=none; rua=mailto:dmarc@your-domain.example; adkim=s; aspf=s`, review reports, then move to `p=quarantine` or `p=reject`. |
| Optional tracking/return-path | Provider-supplied host | Add only the provider records required for the verified sending domain. |

Verify the sender identity with the chosen provider and send a test message to Gmail, Outlook, and Apple Mail. Confirm SPF, DKIM, and DMARC all pass in the received-message headers.

## Manual sign-in checks

1. Request a code and confirm the card displays the masked recipient, an expiry countdown, and six numeric inputs.
2. Paste a six-digit code; it should fill all inputs and submit. Verify the code works once only.
3. Enter a wrong code four times and verify the attempts-left message. On the fifth failure, verify the code is locked and a fresh code is required.
4. Wait for expiry and confirm the inputs and verify button disable while resend is emphasized.
5. Resend after the 60-second cooldown. Confirm the old code fails and the replacement works.
6. Exhaust the three-resend limit and confirm the page shows the exact server-derived time remaining in the 15-minute window.
7. Test on a 320px viewport and a mobile device with email-code autofill enabled.

## Residual operational risks

Email delivery depends on the provider, DNS verification, and a running queue worker. A compromised mailbox can receive sign-in codes; use mailbox MFA and monitor the OTP audit log. The optional new-device email alert is enabled by `OTP_NEW_DEVICE_ALERTS=true`.
