<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        .security-badge { background: #10b981; color: white; padding: 8px 20px; border-radius: 20px; display: inline-block; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 MFA {{ $action == 'enabled' ? 'Enabled' : 'Disabled' }}</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{{ $user->full_name }}</strong>,</p>
            
            @if($action == 'enabled')
                <p>Multi-Factor Authentication has been successfully enabled on your account.</p>
                
                <div style="text-align: center;">
                    <span class="security-badge">✓ Account Secured</span>
                </div>
                
                <p><strong>Your account is now protected with:</strong></p>
                <ul>
                    <li>Time-based one-time passwords (TOTP)</li>
                    <li>10 recovery codes for emergencies</li>
                    <li>Enhanced login security</li>
                </ul>
                
                <p><strong>⚠️ Important:</strong> Keep your recovery codes in a safe place. You'll need them if you lose access to your authenticator app.</p>
            @else
                <p>Multi-Factor Authentication has been disabled on your account.</p>
                
                <p><strong>⚠️ Security Notice:</strong> Your account is now less secure. We strongly recommend re-enabling MFA.</p>
            @endif
            
            <p><strong>Details:</strong></p>
            <ul>
                <li><strong>Time:</strong> {{ now()->format('F j, Y \a\t g:i A') }}</li>
                <li><strong>IP Address:</strong> {{ request()->ip() }}</li>
            </ul>
            
            <p>If you didn't make this change, please contact support immediately.</p>
        </div>
        <div class="footer">
            <p>© {{ date('Y') }} T-Trade. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
