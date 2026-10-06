<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Updated</title>
</head>
<body style="margin:0;background:#f8f6f1;color:#20201d;font-family:Arial,sans-serif;line-height:1.6;">
    <div style="max-width:640px;margin:0 auto;padding:32px 20px;">
        <div style="padding:28px;background:#fffdf9;border:1px solid #e7e1d7;">
            <p style="margin:0 0 18px;color:#b66545;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">3YOS Catering</p>
            <h1 style="margin:0 0 16px;color:#6d3024;font-size:26px;">Your reservation has been updated.</h1>
            <p>Dear {{ $reservation->full_name }},</p>
            <p>Following your discussion with our team, the details for your existing confirmed reservation have been updated. Your reservation remains confirmed.</p>
            <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                <tr><td style="padding:6px 0;color:#6f6d66;">Reservation ID</td><td style="padding:6px 0;font-weight:700;">{{ $reservation->reservation_code ?? '#'.$reservation->id }}</td></tr>
                <tr><td style="padding:6px 0;color:#6f6d66;">Event</td><td style="padding:6px 0;">{{ $reservation->event_type }}</td></tr>
                <tr><td style="padding:6px 0;color:#6f6d66;">Package</td><td style="padding:6px 0;">{{ $reservation->package?->name ?? 'To be confirmed' }}</td></tr>
                <tr><td style="padding:6px 0;color:#6f6d66;">Date</td><td style="padding:6px 0;">{{ \Illuminate\Support\Carbon::parse($reservation->event_date)->format('F j, Y') }}</td></tr>
                <tr><td style="padding:6px 0;color:#6f6d66;">Time</td><td style="padding:6px 0;">{{ \Illuminate\Support\Carbon::parse($reservation->event_time)->format('g:i A') }}</td></tr>
                <tr><td style="padding:6px 0;color:#6f6d66;">Venue</td><td style="padding:6px 0;">{{ $reservation->venue }}</td></tr>
                <tr><td style="padding:6px 0;color:#6f6d66;">Guests</td><td style="padding:6px 0;">{{ number_format($reservation->guest_count) }}</td></tr>
                @if($reservation->total_cost !== null)
                    <tr><td style="padding:6px 0;color:#6f6d66;">Contract Price</td><td style="padding:6px 0;font-weight:700;">&#8369;{{ number_format($reservation->total_cost, 2) }}</td></tr>
                @endif
            </table>
            <p>If you have any questions about these updated details, please contact our team.</p>
            <p style="margin:28px 0 0;color:#6f6d66;">Best regards,<br><strong>3YOS Catering Services &amp; Party Needs</strong></p>
        </div>
    </div>
</body>
</html>
