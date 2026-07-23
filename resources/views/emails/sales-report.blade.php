<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#222;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 12px;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:6px;overflow:hidden;max-width:600px;">
          <tr>
            <td style="background:#940000;color:#fff;padding:16px 20px;font-size:18px;font-weight:bold;">
              {{ $business->name }}
            </td>
          </tr>
          <tr>
            <td style="padding:20px;">
              <p style="margin:0 0 12px;font-size:15px;">Hello {{ $recipientName }},</p>
              <p style="margin:0 0 16px;font-size:14px;line-height:1.5;white-space:pre-wrap;">{{ $bodyMessage }}</p>
              <p style="margin:0;font-size:13px;color:#666;">The detailed sales summary PDF is attached.</p>
            </td>
          </tr>
          <tr>
            <td style="padding:12px 20px;background:#fafafa;font-size:11px;color:#888;border-top:1px solid #eee;">
              Mauzo Link · Automated sales report
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
