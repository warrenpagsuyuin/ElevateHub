<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>Your Elevate sign-in code</title>
</head>
<body style="margin:0;padding:0;background-color:#f2f3f5;color:#202124;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#f2f3f5;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:560px;background-color:#ffffff;border:1px solid #d8dbe0;border-top:5px solid #b7192c;border-radius:8px;">
    <tr><td style="padding:28px 24px 12px;">
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="Elevate" width="150" style="display:block;width:150px;max-width:100%;height:auto;border:0;">
        @else
            <span style="font-size:26px;font-weight:800;letter-spacing:2px;color:#b7192c;">ELEVATE</span>
        @endif
    </td></tr>
    <tr><td style="padding:12px 24px 0;color:#202124;">
        <h1 style="margin:0 0 20px;font-size:24px;line-height:1.3;font-weight:700;">Your sign-in code</h1>
        <p style="margin:0 0 12px;font-size:16px;line-height:1.6;">Hello,</p>
        <p style="margin:0 0 24px;font-size:16px;line-height:1.6;">Enter this one-time code to sign in to Elevate.</p>
    </td></tr>
    <tr><td align="center" style="padding:0 24px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f2f3f5;border:1px solid #d8dbe0;border-radius:6px;">
            <tr><td align="center" style="padding:20px 8px;color:#151515;font-family:'Courier New',Courier,monospace;font-size:32px;line-height:1.4;font-weight:700;letter-spacing:6px;white-space:nowrap;">{{ $otp }}</td></tr>
        </table>
    </td></tr>
    <tr><td style="padding:20px 24px 28px;color:#202124;">
        <p style="margin:0 0 20px;font-size:15px;line-height:1.6;">This code expires in {{ $lifetime }}.<br>Valid until <strong>{{ $expiresAt }}</strong>.</p>
        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;"><strong>Never share this code.</strong> Elevate staff will never ask for it.</p>
        <p style="margin:0;font-size:14px;line-height:1.6;color:#4b5058;">Didn't request this? You can ignore this email. If you keep receiving these, contact your administrator.</p>
    </td></tr>
</table>
<p style="margin:16px 0 0;font-size:12px;line-height:1.5;color:#4b5058;">CCF Elevate &bull; Secure sign-in</p>
</td></tr>
</table>
</body>
</html>
