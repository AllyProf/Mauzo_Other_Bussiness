<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#333;">
@php
    $logoUrl = $business->logoUrl();
    $shortMessage = trim((string) ($body ?? ''));
    if ($shortMessage === '') {
        $shortMessage = 'Your invoice '.$sale->reference_no.' is attached. Open the PDF for the full invoice details.';
    }
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f5f7;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e8e8e8;">
                <tr>
                    <td style="background:#940000;padding:18px 24px;">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $business->name }}" style="max-height:48px;max-width:150px;object-fit:contain;display:block;margin-bottom:8px;">
                        @endif
                        <div style="color:#ffffff;font-size:18px;font-weight:700;">{{ $business->name }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:26px 24px;">
                        <p style="margin:0 0 14px;font-size:15px;line-height:1.5;">
                            Dear {{ $customer->name }},
                        </p>
                        <p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:#444;">
                            {{ $shortMessage }}
                        </p>
                        <p style="margin:0;font-size:14px;line-height:1.5;color:#555;">
                            Regards,<br>
                            <strong>{{ $business->name }}</strong>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:14px 24px 20px;border-top:1px solid #eee;background:#fafafa;text-align:center;">
                        <p style="margin:0;font-size:12px;color:#888;line-height:1.5;">
                            Powered By <strong>EmCa Technologies</strong> —
                            <a href="https://www.emca.tech" style="color:#940000;text-decoration:none;">www.emca.tech</a>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
