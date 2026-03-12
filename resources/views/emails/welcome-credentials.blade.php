<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Account Credentials</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f6f7f8; margin: 0; padding: 0; }
        .wrapper { max-width: 520px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .header { background: #137fec; padding: 28px 40px; text-align: center; }
        .header-logo { margin-bottom: 12px; }
        .header-logo img { height: 42px; object-fit: contain; }
        .header h1 { color: #ffffff; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.3px; }
        .header p { color: rgba(255,255,255,.8); margin: 6px 0 0; font-size: 13px; }
        .body { padding: 36px 40px; }
        .greeting { font-size: 16px; color: #0f172a; font-weight: 600; margin-bottom: 10px; }
        .message { font-size: 14px; color: #64748b; line-height: 1.6; margin-bottom: 28px; }
        .role-badge { display: inline-block; background: #137fec; color: #fff; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 3px 10px; border-radius: 20px; }
        .credentials-box { background: #f0f7ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 24px 28px; margin-bottom: 28px; }
        .cred-row { margin-bottom: 16px; }
        .cred-row:last-child { margin-bottom: 0; }
        .cred-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .cred-value { font-size: 14px; color: #0f172a; font-weight: 600; word-break: break-all; }
        .cred-value a { color: #137fec; text-decoration: none; }
        .cred-value.password { font-family: 'Courier New', monospace; font-size: 17px; color: #137fec; letter-spacing: 2px; }
        .login-btn-wrap { text-align: center; margin-bottom: 28px; }
        .login-btn { display: inline-block; background: #137fec; color: #ffffff !important; text-decoration: none; padding: 13px 36px; border-radius: 8px; font-size: 15px; font-weight: 700; }
        .warning { background: #fff7ed; border-left: 3px solid #f59e0b; padding: 12px 16px; border-radius: 4px; font-size: 13px; color: #92400e; line-height: 1.5; }
        .footer { padding: 20px 40px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #94a3b8; }
        .footer a { color: #137fec; text-decoration: none; }
    </style>
</head>
<body>
    <div class="wrapper">

        <div class="header">
            <h1>Welcome to {{ $siteName }}</h1>
            <p>Your account has been created</p>
        </div>

        <div class="body">
            <div class="greeting">Hello, {{ $userName }}</div>
            <div class="message">
                Your <span class="role-badge">{{ ucfirst($role) }}</span> account is ready.
                Use the credentials below to log in.
            </div>

            <div class="credentials-box">
                <div class="cred-row">
                    <div class="cred-label">Login URL</div>
                    <div class="cred-value"><a href="{{ $loginUrl }}">{{ $loginUrl }}</a></div>
                </div>
                <div class="cred-row">
                    <div class="cred-label">Email / Username</div>
                    <div class="cred-value">{{ $userEmail }}</div>
                </div>
                <div class="cred-row">
                    <div class="cred-label">Password</div>
                    <div class="cred-value password">{{ $plainPassword }}</div>
                </div>
            </div>

            <div class="login-btn-wrap">
                <a href="{{ $loginUrl }}" class="login-btn">Log In to Your Account</a>
            </div>

            <div class="warning">
                For security, please change your password after your first login. Do not share your credentials with anyone.
            </div>
        </div>

        <div class="footer">
            This is an automated message from <a href="{{ $siteUrl }}">{{ $siteName }}</a>. Please do not reply to this email.
        </div>

    </div>
</body>
</html>
