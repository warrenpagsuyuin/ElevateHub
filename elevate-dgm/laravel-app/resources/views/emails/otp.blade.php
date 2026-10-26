@php
    $colors = [
        'outer' => '#F3F4F6', 'border' => '#D9DCE1', 'accent' => '#B42318',
        'charcoal' => '#1A1A1A', 'white' => '#FFFFFF', 'text' => '#111111',
        'panelBorder' => '#2F2F2F', 'muted' => '#B8B8B8', 'light' => '#F2F2F2',
        'noteBorder' => '#E0E0E0', 'secondary' => '#525252', 'darkOuter' => '#0A0A0A',
        'darkNote' => '#242424', 'darkPanelBorder' => '#3A3A3A',
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>Your Elevate sign-in code</title>
    <style>
        @media screen and (max-width: 480px) {
            .outer-padding { padding:16px !important; }
            .body-padding { padding:20px !important; }
            .header-padding { padding:28px 20px !important; }
            .code-digits { font-size:30px !important; letter-spacing:0.25em !important; padding-left:0.25em !important; }
        }
        @media (prefers-color-scheme: dark) {
            .outer { background-color:{{ $colors['darkOuter'] }} !important; }
            .card, .mail-body { background-color:{{ $colors['charcoal'] }} !important; border-color:{{ $colors['panelBorder'] }} !important; }
            .primary { color:{{ $colors['light'] }} !important; }
            .secondary { color:{{ $colors['muted'] }} !important; }
            .security { background-color:{{ $colors['darkNote'] }} !important; border-color:{{ $colors['panelBorder'] }} !important; }
            .code-panel { background-color:{{ $colors['charcoal'] }} !important; border-color:{{ $colors['darkPanelBorder'] }} !important; }
        }
    </style>
</head>
<body class="outer" bgcolor="{{ $colors['outer'] }}" style="margin:0;padding:0;background-color:{{ $colors['outer'] }};color:{{ $colors['text'] }};font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">Use this one-time code to sign in. It expires in 5 minutes.</div>
<table role="presentation" class="outer" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="{{ $colors['outer'] }}" style="width:100%;background-color:{{ $colors['outer'] }};">
<tr style="vertical-align:top;"><td class="outer-padding" align="center" style="padding:24px;">
<!--[if mso]><table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px;"><tr style="vertical-align:top;"><td style="padding:0;"><![endif]-->
<table role="presentation" class="card" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="{{ $colors['white'] }}" style="width:100%;max-width:600px;background-color:{{ $colors['white'] }};border:1px solid {{ $colors['border'] }};border-radius:12px;border-spacing:0;overflow:hidden;">
    <tr style="vertical-align:top;"><td height="4" bgcolor="{{ $colors['accent'] }}" style="height:4px;font-size:0;line-height:4px;background-color:{{ $colors['accent'] }};border-radius:12px 12px 0 0;">&nbsp;</td></tr>
    <tr style="vertical-align:top;"><td class="header-padding" bgcolor="{{ $colors['charcoal'] }}" style="padding:28px 32px;background-color:{{ $colors['charcoal'] }};">
        <img src="{{ $message->embed(public_path('images/email/elevate-logo.png')) }}" alt="Elevate" width="150" height="28" style="display:block;border:0;outline:none;text-decoration:none;width:150px;height:28px;color:{{ $colors['white'] }};font-size:24px;font-weight:bold;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    </td></tr>
    <tr style="vertical-align:top;"><td class="mail-body body-padding primary" bgcolor="{{ $colors['white'] }}" style="padding:32px;background-color:{{ $colors['white'] }};color:{{ $colors['text'] }};border-radius:0 0 12px 12px;">
        <h1 class="primary" style="margin:0 0 16px;font-size:24px;line-height:1.3;font-weight:700;color:{{ $colors['text'] }};">Your sign-in code</h1>
        <p class="primary" style="margin:0 0 16px;font-size:16px;line-height:1.5;color:{{ $colors['text'] }};">Hello,</p>
        <p class="primary" style="margin:0 0 24px;font-size:16px;line-height:1.5;color:{{ $colors['text'] }};">Enter this one-time code to sign in to Elevate.</p>
        <table role="presentation" class="code-panel" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="{{ $colors['charcoal'] }}" style="width:100%;background-color:{{ $colors['charcoal'] }};border:1px solid {{ $colors['panelBorder'] }};border-radius:12px;">
            <tr style="vertical-align:top;"><td align="center" style="padding:24px;">
                <p style="margin:0 0 8px;color:{{ $colors['muted'] }};font-size:12px;line-height:1.5;text-transform:uppercase;letter-spacing:0.1em;">One-time code</p>
                <p class="code-digits" style="margin:0;padding-left:0.35em;color:{{ $colors['light'] }};font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace;font-size:36px;line-height:1.4;font-weight:700;letter-spacing:0.35em;white-space:nowrap;">{{ $otp }}</p>
            </td></tr>
        </table>
        <p class="primary" style="margin:24px 0;font-size:16px;line-height:1.5;color:{{ $colors['text'] }};">This code expires in {{ $lifetime }}.<br style="line-height:1.5;">Valid until <strong style="font-weight:700;">{{ $expiresAt }}</strong>.</p>
        <table role="presentation" class="security" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="{{ $colors['outer'] }}" style="width:100%;background-color:{{ $colors['outer'] }};border:1px solid {{ $colors['noteBorder'] }};border-radius:8px;">
            <tr style="vertical-align:top;"><td class="primary" style="padding:16px;font-size:16px;line-height:1.5;color:{{ $colors['text'] }};"><strong style="font-weight:700;">Never share this code.</strong> Elevate staff will never ask for it.</td></tr>
        </table>
        <p class="secondary" style="margin:24px 0 0;font-size:14px;line-height:1.5;color:{{ $colors['secondary'] }};">Didn't request this? You can ignore this email. If you keep receiving these, contact your administrator.</p>
    </td></tr>
</table>
<!--[if mso]></td></tr></table><![endif]-->
<p class="secondary" style="margin:16px 0 0;font-size:12px;line-height:1.5;color:{{ $colors['secondary'] }};">CCF Elevate &bull; Secure sign-in</p>
</td></tr>
</table>
</body>
</html>
