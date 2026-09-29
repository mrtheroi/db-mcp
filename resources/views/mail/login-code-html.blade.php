<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Your memry login code</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6;">
    {{-- Hidden inbox preview text; the padding keeps the code from showing after it. --}}
    <div style="display:none; max-height:0; max-width:0; overflow:hidden; opacity:0; mso-hide:all; font-size:1px; line-height:1px; color:#f3f4f6;">
        Your memry login code expires in 10 minutes.{!! str_repeat('&#847;&zwnj;&nbsp;', 60) !!}
    </div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f3f4f6" style="background-color:#f3f4f6;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="max-width:480px; background-color:#ffffff; border-radius:12px;">
                    <tr>
                        <td align="center" bgcolor="#ffffff" style="padding:32px 32px 8px; background-color:#ffffff; border-radius:12px 12px 0 0;">
                            <img src="{{ asset('images/memry-logo-horizontal.png') }}" width="280" alt="memry" style="display:block; width:280px; max-width:100%; height:auto; border:0; outline:none; text-decoration:none;">
                        </td>
                    </tr>
                    <tr>
                        <td align="center" bgcolor="#ffffff" style="padding:8px 32px 0; background-color:#ffffff; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; font-size:20px; line-height:28px; font-weight:bold; color:#05313a;">
                            Your login code
                        </td>
                    </tr>
                    <tr>
                        <td align="center" bgcolor="#ffffff" style="padding:20px 32px; background-color:#ffffff;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" bgcolor="#fff1e7" style="padding:16px 24px 16px 32px; background-color:#fff1e7; border:1px solid #ffd2b3; border-radius:8px; font-family:'SFMono-Regular', Menlo, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size:32px; line-height:40px; font-weight:bold; letter-spacing:8px; color:#05313a;">{{ $code }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" bgcolor="#ffffff" style="padding:0 32px 32px; background-color:#ffffff; border-radius:0 0 12px 12px; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; font-size:14px; line-height:22px; color:#6b7280;">
                            It expires in 10 minutes.<br>
                            If you did not request it, you can ignore this email.
                        </td>
                    </tr>
                </table>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:480px;">
                    <tr>
                        <td align="center" style="padding:16px 32px 0; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; font-size:12px; line-height:18px; color:#9ca3af;">
                            memry &middot; memory for your AI agents
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
