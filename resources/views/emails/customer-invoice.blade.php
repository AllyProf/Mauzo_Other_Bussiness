<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <p>Dear {{ $customer->name }},</p>

    <div style="white-space: pre-wrap;">{{ $body }}</div>

    <p style="margin-top: 24px;">
        Your invoice <strong>{{ $sale->reference_no }}</strong> is attached to this email.
        Open the attachment to view or print it.
    </p>

    <p style="margin-top: 24px; color: #666; font-size: 13px;">
        — {{ $business->name }}
    </p>
</body>
</html>
