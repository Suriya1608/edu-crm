<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Verification Code</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f6f7f8; margin: 0; padding: 0; }
        .wrapper { max-width: 480px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .header { background: #137fec; padding: 32px 40px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 22px; font-weight: 700; letter-spacing: -0.3px; }
        .body { padding: 36px 40px; }
        .greeting { font-size: 16px; color: #0f172a; font-weight: 600; margin-bottom: 12px; }
        .message { font-size: 14px; color: #64748b; line-height: 1.6; margin-bottom: 28px; }
        .otp-box { background: #f0f7ff; border: 2px dashed #137fec; border-radius: 10px; text-align: center; padding: 20px; margin-bottom: 28px; }
        .otp-code { font-size: 38px; font-weight: 800; letter-spacing: 10px; color: #137fec; font-family: 'Courier New', monospace; }
        .otp-expiry { font-size: 12px; color: #64748b; margin-top: 8px; }
        .warning { background: #fff7ed; border-left: 3px solid #f59e0b; padding: 12px 16px; border-radius: 4px; font-size: 13px; color: #92400e; margin-bottom: 20px; }
        .footer { padding: 20px 40px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>Login Verification</h1>
        </div>
        <div class="body">
            <div class="greeting">Hello, {{ $userName }}</div>
            <div class="message">
                Use the verification code below to complete your login. This code is valid for <strong>10 minutes</strong>.
            </div>
            <div class="otp-box">
                <div class="otp-code">{{ $otp }}</div>
                <div class="otp-expiry">Expires in 10 minutes</div>
            </div>
            <div class="warning">
                If you did not attempt to log in, please ignore this email and consider changing your password immediately.
            </div>
        </div>
        <div class="footer">
            This is an automated message. Please do not reply to this email.
        </div>
    </div>
</body>
</html>
