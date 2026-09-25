<!doctype html>
<html lang="en">
<body style="margin:0;background:#edf4f2;font-family:Arial,sans-serif;color:#203039">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#edf4f2;padding:32px 16px"><tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#fff;border-radius:18px;overflow:hidden">
<tr><td style="background:#0c302b;padding:24px 32px;color:#fff;font-size:24px;font-weight:700">TouchNRelief</td></tr>
<tr><td style="padding:34px 32px">
<p style="margin:0 0 12px;color:#087b57;font-size:13px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase">Account verification</p>
<h1 style="margin:0 0 18px;font-size:28px">Your verification code</h1>
<p style="margin:0 0 22px;line-height:1.6">{{ $name ? 'Hello '.$name.',' : 'Hello,' }} use this code to {{ $purposeLabel }}.</p>
<div style="padding:18px;background:#edf8f4;border:1px solid #b9ddcf;border-radius:12px;text-align:center;font-size:34px;font-weight:700;letter-spacing:9px;color:#076b4c">{{ $code }}</div>
<p style="margin:22px 0 0;color:#5d7079;line-height:1.6">This code expires in {{ $expiresIn }} minutes. If you did not request it, you can ignore this email.</p>
</td></tr></table></td></tr></table>
</body>
</html>
