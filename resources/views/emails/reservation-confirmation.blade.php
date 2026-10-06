<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your reservation ID</title>
</head>
<body style="margin:0;background:#f8f6f1;color:#20201d;font-family:Arial,sans-serif;line-height:1.6;">
    <div style="max-width:640px;margin:0 auto;padding:32px 20px;">
        <div style="padding:28px;background:#fffdf9;border:1px solid #e7e1d7;">
            <p style="margin:0 0 18px;color:#b66545;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">3YOS Catering</p>
            <h1 style="margin:0 0 16px;color:#6d3024;font-size:26px;">We received your reservation request.</h1>
            <p>Hello {{ $reservation->full_name }},</p>
            <p>Your request is being reviewed. Keep this reservation ID to check its status:</p>
            <p style="padding:14px;background:#f3eee7;font-size:22px;font-weight:700;letter-spacing:2px;text-align:center;">{{ $reservation->reservation_code }}</p>
            <p>Event: {{ $reservation->event_type }}<br>Date: {{ $reservation->event_date }}<br>Guests: {{ number_format($reservation->guest_count) }}</p>
            <p><a href="{{ route('reservation.status', ['code' => $reservation->reservation_code]) }}" style="color:#6d3024;font-weight:700;">Check reservation status</a></p>
            <p style="margin:28px 0 0;color:#6f6d66;">Warm regards,<br><strong>3YOS Catering Services &amp; Party Needs</strong></p>
        </div>
    </div>
</body>
</html>