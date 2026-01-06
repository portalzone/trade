<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
        .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        .success-badge { background: #10b981; color: white; padding: 5px 15px; border-radius: 20px; display: inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 KYC Verification Approved!</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{{ $user->full_name }}</strong>,</p>
            
            <p>Great news! Your KYC verification has been approved.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <span class="success-badge">Tier {{ $tier }} Verified</span>
            </div>
            
            @if($tier == 2)
                <p><strong>You can now:</strong></p>
                <ul>
                    <li>Create business storefronts</li>
                    <li>Access higher transaction limits</li>
                    <li>List products for sale</li>
                    <li>Accept payments from buyers</li>
                </ul>
            @elseif($tier == 3)
                <p><strong>You now have enterprise access:</strong></p>
                <ul>
                    <li>Unlimited transaction limits</li>
                    <li>Priority support</li>
                    <li>Advanced analytics</li>
                    <li>Dedicated account manager</li>
                </ul>
            @endif
            
            <div style="text-align: center;">
                <a href="{{ config('app.frontend_url') }}/dashboard" class="button">Go to Dashboard</a>
            </div>
        </div>
        <div class="footer">
            <p>© {{ date('Y') }} T-Trade. All rights reserved.</p>
            <p>This is an automated email. Please do not reply.</p>
        </div>
    </div>
</body>
</html>
