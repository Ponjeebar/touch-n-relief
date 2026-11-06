<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your TouchNRelief verification code</title>
</head>
<body style="margin:0;padding:0;background:#edf4f2;font-family:Arial,Helvetica,sans-serif;color:#1f2b33;">
    <div style="display:none;max-height:0;overflow:hidden;font-size:1px;line-height:1px;">Your code to {{ $purposeLabel }} expires in {{ $expiresIn }} minutes.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#edf4f2;border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;">
        <tr><td align="center" style="padding:24px 12px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;">
                <tr><td style="padding:22px 24px;background:#0c302b;border-radius:8px 8px 0 0;">
                    <table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;">
                        <tr>
                            <td style="padding-right:12px;vertical-align:middle;"><img src="{{ $logoSrc ?: asset('images/dashboard/logo.png') }}" width="48" height="48" alt="" style="display:block;width:48px;height:48px;"></td>
                            <td style="vertical-align:middle;">
                                <p style="margin:0;color:#ffffff;font-family:Georgia,serif;font-size:25px;line-height:1.2;font-weight:bold;">TouchNRelief</p>
                                <p style="margin:5px 0 0;color:#c9dfd1;font-size:12px;line-height:1.4;">Buenos Touch&eacute; Spa</p>
                            </td>
                        </tr>
                    </table>
                </td></tr>
                <tr><td style="padding:28px 24px;background:#ffffff;border:1px solid #d6e4e3;border-top:0;border-radius:0 0 8px 8px;">
                    <h1 style="margin:0 0 20px;color:#1f2b33;font-size:25px;line-height:1.3;">Your verification code</h1>
                    <p style="margin:0 0 8px;color:#354650;font-size:15px;line-height:1.6;">{{ $name ? 'Hello '.$name.',' : 'Hello,' }}</p>
                    <p style="margin:0 0 24px;color:#354650;font-size:15px;line-height:1.6;">Use this code to {{ $purposeLabel }}.</p>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;">
                        <tr><td align="center" style="padding:22px 8px;background:#edf8f4;border:1px solid #b9ddcf;border-radius:6px;">
                            <p style="margin:0;color:#076b4c;font-family:'Courier New',monospace;font-size:34px;font-weight:bold;line-height:1.3;letter-spacing:5px;">{{ $code }}</p>
                            <p style="margin:10px 0 0;color:#354650;font-size:13px;line-height:1.5;">Expires in <strong>{{ $expiresIn }} minutes</strong></p>
                        </td></tr>
                    </table>
                    <p style="margin:20px 0 0;color:#354650;font-size:14px;line-height:1.6;">Return to the TouchNRelief page where you requested this code and enter all six digits.</p>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:24px;border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;">
                        <tr><td style="padding-top:18px;border-top:1px solid #d6e4e3;">
                            <p style="margin:0 0 8px;color:#1f2b33;font-size:13px;font-weight:bold;line-height:1.6;">Do not share this code with anyone.</p>
                            <p style="margin:0;color:#586f7c;font-size:13px;line-height:1.6;">If you did not request this code, you can ignore this email.</p>
                        </td></tr>
                    </table>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
