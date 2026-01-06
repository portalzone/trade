<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
        .button { display: inline-block; background: #ef4444; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        .reason-box { background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>❌ KYC Verification Not Approved</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{{ $user->full_name }}</strong>,</p>
            
            <p>We regret to inform you that your Tier {{ $tier }} KYC verification was not approved.</p>
            
            <div class="reason-box">
                <strong>Reason:</strong><br>
                {{ $reason }}
            </div>
            
            <p><strong>Next Steps:</strong></p>
            <ul>
                <li>Review the reason for rejection carefully</li>
                <li>Gather the correct documentation</li>
                <li>Resubmit your application when ready</li>
            </ul>
            
            <div style="text-align: center;">
                <a href="{{ config('app.frontend_url') }}/kyc" class="button">Resubmit Application</a>
            </div>
            
            <p>If you have any questions, please contact our support team.</p>
        </div>
        <div class="footer">
            <p>© {{ date('Y') }} T-Trade. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
