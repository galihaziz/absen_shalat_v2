<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset password</title>
</head>
<body style="margin:0;background:#f4f7f1;color:#243126;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #e1e9dc;border-radius:12px;">
                    <tr>
                        <td align="center" style="padding:28px 24px 12px;">
                            <a href="{{ $homeUrl }}" style="text-decoration:none;">
                                <img src="{{ $logoUrl }}" alt="Logo {{ $appName }}" width="112" style="display:block;width:112px;height:auto;border:0;">
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 32px;">
                            <h1 style="margin:0 0 16px;text-align:center;font-size:22px;color:#216b2b;">Reset Password Admin</h1>
                            <p style="margin:0 0 16px;line-height:1.6;">Kami menerima permintaan untuk mengatur ulang password akun admin {{ $appName }}.</p>
                            <p style="margin:0 0 24px;line-height:1.6;">Klik tombol di bawah untuk membuat password baru. Tautan ini berlaku selama {{ $expireMinutes }} menit.</p>
                            <p style="margin:0 0 24px;text-align:center;">
                                <a href="{{ $resetUrl }}" style="display:inline-block;padding:12px 22px;border-radius:6px;background:#23832c;color:#ffffff;text-decoration:none;font-weight:bold;">Atur Ulang Password</a>
                            </p>
                            <p style="margin:0 0 8px;font-size:13px;line-height:1.6;">Jika tombol tidak berfungsi, salin dan buka tautan berikut di browser:</p>
                            <p style="margin:0 0 20px;word-break:break-all;font-size:13px;line-height:1.6;"><a href="{{ $resetUrl }}" style="color:#216b2b;">{{ $resetUrl }}</a></p>
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#59645a;">Jika Anda tidak meminta reset password, abaikan email ini. Password Anda tidak akan berubah.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px;border-top:1px solid #e8eee5;text-align:center;font-size:12px;color:#68736a;">
                            {{ $appName }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
