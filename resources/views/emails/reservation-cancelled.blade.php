<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Cancelled</title>
</head>
<body style="margin:0;background:#f8f6f1;color:#20201d;font-family:Arial,sans-serif;line-height:1.6;">
    <div style="max-width:640px;margin:0 auto;padding:32px 20px;">
        <div style="padding:28px;background:#fffdf9;border:1px solid #e7e1d7;">
            <p style="margin:0 0 18px;color:#b66545;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">3YOS Catering</p>
            <h1 style="margin:0 0 16px;color:#6d3024;font-size:26px;">Your reservation has been cancelled.</h1>
            <p>Dear {{ $reservation->full_name }},</p>
            <p>We are writing to inform you that your reservation has been cancelled.</p>
            <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                <tr><td style="padding:6px 0;color:#6f6d66;">Reservation ID</td><td style="padding:6px 0;font-weight:700;">{{ $reservation->reservation_code }}</td></tr>
                <tr><td style="padding:6px 0;color:#6f6d66;">Event</td><td style="padding:6px 0;">{{ $reservation->event_type }}</td></tr>
                <tr><td style="padding:6px 0;color:#6f6d66;">Package</td><td style="padding:6px 0;">{{ $reservation->package?->name ?? 'N/A' }}</td></tr>
                <tr><td style="padding:6px 0;color:#6f6d66;">Date</td><td style="padding:6px 0;">{{ \Illuminate\Support\Carbon::parse($reservation->event_date)->format('F j, Y') }}</td></tr>
                <tr><td style="padding:6px 0;color:#6f6d66;">Time</td><td style="padding:6px 0;">{{ $reservation->event_time }}</td></tr>
                <tr><td style="padding:6px 0;color:#6f6d66;">Guests</td><td style="padding:6px 0;">{{ number_format($reservation->guest_count) }}</td></tr>
                @if($reservation->total_cost !== null)
                    <tr><td style="padding:6px 0;color:#6f6d66;">Contract Price</td><td style="padding:6px 0;">&#8369;{{ number_format($reservation->total_cost, 2) }}</td></tr>
                @endif
            </table>
            <p>If you have any questions, please contact us.</p>
            <p style="margin:28px 0 0;color:#6f6d66;">Best regards,<br><strong>3YOS Catering Services &amp; Party Needs</strong></p>
        </div>
    </div>
</body>
</html>
