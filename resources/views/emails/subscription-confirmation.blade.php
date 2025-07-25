<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Confirmed</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #ffffff;
            padding: 30px;
            border: 1px solid #e9ecef;
        }
        .success-box {
            background-color: #d4edda;
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .plan-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 0 0 8px 8px;
            font-size: 14px;
            color: #6c757d;
        }
        .btn {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
        .price {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0; color: #007bff;">Food Management System</h1>
        <p style="margin: 10px 0 0 0; color: #6c757d;">Subscription Confirmation</p>
    </div>
    
    <div class="content">
        <div class="success-box">
            <h2 style="color: #28a745; margin: 0;">✅ Subscription Confirmed!</h2>
            <p style="margin: 10px 0 0 0;">Welcome to {{ $plan->name ?? 'Premium' }} membership</p>
        </div>
        
        <p>Hello {{ $user->name }},</p>
        <p>Thank you for subscribing to our {{ $plan->name ?? 'Premium' }} plan! Your subscription has been successfully activated and you now have access to all premium features.</p>
        
        <div class="plan-details">
            <h3>Your Subscription Details:</h3>
            <ul>
                <li><strong>Plan:</strong> {{ $plan->name ?? 'Premium' }}</li>
                <li><strong>Price:</strong> <span class="price">${{ $plan->price ?? '0.00' }}/month</span></li>
                <li><strong>Started:</strong> {{ $user->subscription_started_at ? $user->subscription_started_at->format('F j, Y') : now()->format('F j, Y') }}</li>
                <li><strong>Next Billing:</strong> {{ $user->subscription_ends_at ? $user->subscription_ends_at->format('F j, Y') : now()->addMonth()->format('F j, Y') }}</li>
                <li><strong>Auto-Renewal:</strong> {{ $user->auto_renew ? 'Enabled' : 'Disabled' }}</li>
            </ul>
        </div>
        
        @if($plan && $plan->name !== 'Basic')
        <div style="background-color: #e7f3ff; border-radius: 8px; padding: 20px; margin: 20px 0;">
            <h3>🚀 Premium Features Now Available:</h3>
            <ul>
                <li>Unlimited product creation</li>
                <li>Advanced nutritional analysis</li>
                <li>Custom label templates</li>
                <li>Bulk operations</li>
                <li>Priority customer support</li>
                <li>API access</li>
                <li>Advanced reporting</li>
            </ul>
        </div>
        @endif
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ config('app.frontend_url') }}/dashboard" class="btn">Access Your Dashboard</a>
        </div>
        
        <p><strong>Important Information:</strong></p>
        <ul>
            <li>Your subscription will automatically renew unless cancelled</li>
            <li>You can manage your subscription anytime from your account settings</li>
            <li>All charges will appear as "Food Management System" on your statement</li>
            <li>You'll receive a receipt for each billing cycle</li>
        </ul>
        
        <p>If you have any questions about your subscription or need help getting started with premium features, please don't hesitate to contact our support team.</p>
        
        <p>Thank you for your business!</p>
        
        <p>Best regards,<br>
        <strong>Food Management System Team</strong></p>
    </div>
    
    <div class="footer">
        <p>This is an automated email. Please do not reply to this message.</p>
        <p>© {{ date('Y') }} Food Management System. All rights reserved.</p>
    </div>
</body>
</html>