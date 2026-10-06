<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New reservation received</title>
</head>
<body style="margin:0;background:#f8f6f1;color:#20201d;font-family:Arial,sans-serif;line-height:1.6;">
    <div style="max-width:640px;margin:0 auto;padding:32px 20px;">
        <div style="padding:28px;background:#fffdf9;border:1px solid #e7e1d7;">
            <p style="margin:0 0 18px;color:#b66545;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">3YOS Catering</p>
            <h1 style="margin:0 0 16px;color:#6d3024;font-size:26px;">New reservation received</h1>
            <p>A reservation has been submitted and is awaiting review.</p>
            <p>
                <strong>Customer:</strong> {{ $reservation->full_name }}<br>
                <strong>Reservation ID:</strong> #{{ $reservation->id }}<br>
                <strong>Reservation code:</strong> {{ $reservation->reservation_code ?: 'Not assigned' }}<br>
                <strong>Event:</strong> {{ $reservation->event_type }}<br>
                <strong>Event date:</strong> {{ \Illuminate\Support\Carbon::parse($reservation->event_date)->format('F j, Y') }}<br>
                <strong>Event time:</strong> {{ $reservation->event_time }}<br>
                <strong>Venue:</strong> {{ $reservation->venue }}<br>
                <strong>Guests:</strong> {{ number_format($reservation->guest_count) }}<br>
                <strong>Package:</strong> {{ $reservation->package?->name ?? 'Not specified' }}<br>
                <strong>Additional services:</strong> {{ $reservation->additional_services ?: 'None specified' }}<br>
                <strong>Status:</strong> {{ \App\Models\Reservation::statusLabel($reservation->status) }}<br>
                <strong>Submitted:</strong> {{ $reservation->created_at?->timezone(config('app.timezone'))->format('F j, Y \a\t g:i A') ?? 'Unavailable' }}
            </p>
            <p style="margin:24px 0;">
                <a href="{{ route('admin.reservations.show', $reservation) }}" style="display:inline-block;padding:12px 20px;background:#6d3024;color:#fffdf9;text-decoration:none;font-weight:700;border-radius:4px;">View Reservation</a>
            </p>
            <p style="margin:28px 0 0;color:#6f6d66;">3YOS Catering Management System</p>
        </div>
    </div>
</body>
</html>
