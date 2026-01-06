<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
        .button { display: inline-block; background: #f59e0b; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        .info-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚖️ Dispute Update</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{{ $user->full_name }}</strong>,</p>
            
            <p>There's an update on your dispute for Order #{{ $dispute->order_id }}.</p>
            
            <div class="info-box">
                <strong>Status:</strong> {{ $dispute->dispute_status }}<br>
                <strong>Resolution:</strong> {{ $resolution ?? 'Pending review' }}
            </div>
            
            @if($dispute->dispute_status == 'RESOLVED_BUYER')
                <p>✅ The dispute has been resolved in your favor as the buyer.</p>
            @elseif($dispute->dispute_status == 'RESOLVED_SELLER')
                <p>✅ The dispute has been resolved in favor of the seller.</p>
            @elseif($dispute->dispute_status == 'RESOLVED_REFUND')
                <p>✅ A refund has been issued for this dispute.</p>
            @endif
            
            <div style="text-align: center;">
                <a href="{{ config('app.frontend_url') }}/orders/{{ $dispute->order_id }}" class="button">View Order</a>
            </div>
        </div>
        <div class="footer">
            <p>© {{ date('Y') }} T-Trade. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
