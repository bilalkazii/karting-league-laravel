<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitation</title>
</head>
<body style="margin:0;padding:0;background:#0b0d10;font-family:Arial,Helvetica,sans-serif;color:#e8ecef;">
    <div style="max-width:560px;margin:0 auto;padding:32px 24px;">
        <p style="font-size:12px;letter-spacing:.2em;text-transform:uppercase;color:#ef4444;margin:0 0 8px;">Karting</p>
        <h1 style="font-size:22px;margin:0 0 12px;color:#ffffff;">You're invited to {{ $group->name }}</h1>
        <p style="font-size:14px;line-height:1.6;color:#9aa4ad;margin:0 0 20px;">
            You have been invited to join the group <strong style="color:#ffffff;">{{ $group->name }}</strong> on Karting.
            Open the link below and sign in to accept. This invitation is valid until
            <strong style="color:#ffffff;">{{ $expiresAt->format('d M Y') }}</strong>.
        </p>
        <p style="margin:0 0 24px;">
            <a href="{{ $inviteUrl }}" style="display:inline-block;background:#ef4444;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px;">Accept invitation</a>
        </p>
        <p style="font-size:12px;color:#6b7379;line-height:1.6;margin:0 0 8px;">Or paste this link into your browser:</p>
        <p style="font-size:12px;color:#9aa4ad;word-break:break-all;margin:0 0 24px;">{{ $inviteUrl }}</p>
        <p style="font-size:11px;color:#6b7379;margin:0;">If you were not expecting this invitation, you can ignore this email.</p>
    </div>
</body>
</html>
