<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset your TouchNRelief password</title>
</head>
<body style="margin:0;padding:0;background:#edf3f1;color:#1f2b33;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;font-size:1px;line-height:1px;color:#edf3f1;max-height:0;max-width:0;opacity:0;overflow:hidden;">
        Use this secure link to set a new password for your TouchNRelief account.
    </div>
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#edf3f1;border-collapse:collapse;">
        <tr>
            <td align="center" style="padding:32px 16px 40px;">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;border-collapse:collapse;">
                    <tr>
                        <td style="background:#102a28;padding:23px 30px;border-radius:20px 20px 0 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                <tr>
                                    <td style="vertical-align:middle;padding-right:12px;">
                                        <img src="{{ $logoSrc ?? 'cid:touch-n-relief-logo' }}" alt="" width="48" height="48" style="display:block;width:48px;height:48px;border-radius:50%;border:2px solid #c7e9dc;object-fit:cover;">
                                    </td>
                                    <td style="vertical-align:middle;color:#ffffff;font-family:Georgia,serif;font-size:25px;font-weight:bold;letter-spacing:.2px;">TouchNRelief</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff;padding:38px 34px 34px;border:1px solid #dce9e4;border-top:0;border-radius:0 0 20px 20px;">
                            <div style="color:#04724d;font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;margin-bottom:12px;">Account security</div>
                            <h1 style="color:#1f2b33;font-size:27px;line-height:1.25;margin:0 0 20px;font-weight:700;">Reset your password</h1>
                            <p style="font-size:16px;line-height:1.7;margin:0 0 16px;color:#354650;">Hello{{ $name !== '' ? ' '.$name : '' }},</p>
                            <p style="font-size:16px;line-height:1.7;margin:0 0 28px;color:#354650;">We received a request to reset the password for your TouchNRelief account. Select the button below to choose a new password.</p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 28px;">
                                <tr>
                                    <td align="center" bgcolor="#04724d" style="border-radius:10px;background:#04724d;">
                                        <a href="{{ $resetUrl }}" style="display:inline-block;padding:15px 26px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold;line-height:1.2;">Reset password</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="font-size:14px;line-height:1.6;margin:0 0 17px;color:#586f7c;">This link expires in {{ $expiresIn }} minutes. If you didn’t request a password reset, you can safely ignore this email.</p>
                            <div style="border-top:1px solid #dce9e4;padding-top:20px;margin-top:25px;">
                                <p style="font-size:12px;line-height:1.6;margin:0 0 8px;color:#586f7c;">If the button doesn’t work, copy this link into your browser:</p>
                                <p style="font-size:12px;line-height:1.6;margin:0;word-break:break-all;"><a href="{{ $resetUrl }}" style="color:#04724d;text-decoration:underline;">{{ $resetUrl }}</a></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 12px 0;color:#586f7c;font-size:12px;line-height:1.6;">TouchNRelief &middot; Care that stays with you</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
