<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>New sign-in to your Elevate account</title>
</head>
<body style="margin:0;padding:0;background-color:#f2f3f5;color:#202124;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#f2f3f5;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:560px;background-color:#ffffff;border:1px solid #d8dbe0;border-top:5px solid #b7192c;border-radius:8px;">
    <tr><td style="padding:28px 24px 12px;">
        <span style="font-size:26px;font-weight:800;letter-spacing:2px;color:#b7192c;">ELEVATE</span>
    </td></tr>
    <tr><td style="padding:12px 24px 28px;color:#202124;">
        <h1 style="margin:0 0 20px;font-size:22px;line-height:1.3;font-weight:700;">New sign-in detected</h1>
        <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Your Elevate account was just signed in from a device we haven't seen before.</p>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 20px;font-size:14px;line-height:1.6;">
            <tr><td style="padding:2px 12px 2px 0;color:#4b5058;">When</td><td style="padding:2px 0;"><strong>{{ $when }}</strong></td></tr>
            <tr><td style="padding:2px 12px 2px 0;color:#4b5058;">Device</td><td style="padding:2px 0;">{{ $device }}</td></tr>
            <tr><td style="padding:2px 12px 2px 0;color:#4b5058;">IP address</td><td style="padding:2px 0;">{{ $ip }}</td></tr>
        </table>
        <p style="margin:0 0 12px;font-size:15px;line-height:1.6;">If this was you, no action is needed.</p>
        <p style="margin:0;font-size:15px;line-height:1.6;"><strong>Not you?</strong> Contact your administrator right away.</p>
    </td></tr>
</table>
<p style="margin:16px 0 0;font-size:12px;line-height:1.5;color:#4b5058;">CCF Elevate &bull; Security notice</p>
</td></tr>
</table>
</body>
</html>
