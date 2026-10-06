<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine ?? 'Reply from 3YOS Catering' }}</title>
</head>
<body style="margin:0;background:#f8f6f1;color:#20201d;font-family:Arial,sans-serif;line-height:1.6;">
    <div style="max-width:640px;margin:0 auto;padding:32px 20px;">
        <div style="padding:28px;background:#fffdf9;border:1px solid #e7e1d7;">
            <p style="margin:0 0 18px;color:#b66545;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">3YOS Catering</p>
            <h1 style="margin:0 0 20px;color:#6d3024;font-size:26px;">Hello {{ $customerName }},</h1>
            <div style="white-space:pre-line;">{{ $reply }}</div>
            <p style="margin:28px 0 0;color:#6f6d66;">Warm regards,<br><strong>3YOS Catering Services &amp; Party Needs</strong></p>
        </div>
    </div>
</body>
</html>
