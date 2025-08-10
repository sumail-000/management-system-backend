<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Cancelled</title>
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
        .cancellation-box {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .details-box {
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
            margin: 10px 5px;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .important {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0; color: #007bff;">Food Management System</h1>
        <p style="margin: 10px 0 0 0; color: #6c757d;">Subscription Cancellation</p>
    </div>
    
    <div class="content">
        <div class="cancellation-box">
            <h2 style="color: #856404; margin: 0;">⚠️ Subscription Cancelled</h2>
            <p style="margin: 10px 0 0 0;">Your {{ $plan->name ?? 'Premium' }} subscription has been cancelled</p>
        </div>
        
        <p>Hello {{ $user->name }},</p>
        <p>We're sorry to see you go! Your subscription to our {{ $plan->name ?? 'Premium' }} plan has been successfully cancelled as requested.</p>
        
        <div class="details-box">
            <h3>Cancellation Details:</h3>
            <ul>
                <li><strong>Plan:</strong> {{ $plan->name ?? 'Premium' }}</li>
                <li><strong>Cancellation Date:</strong> {{ $user->cancelled_at ? $user->cancelled_at->format('F j, Y') : now()->format('F j, Y') }}</li>
                <li><strong>Service End Date:</strong> {{ $user->subscription_ends_at ? $user->subscription_ends_at->format('F j, Y') : now()->format('F j, Y') }}</li>
                @if($reason && $reason !== 'Unknown reason')
                <li><strong>Reason:</strong> {{ $reason }}</li>
                @elseif(!$reason || $reason === 'Unknown reason')
                <li><strong>Reason:</strong> Unknown reason</li>
                @endif
            </ul>
        </div>
        
        <div class="important">
            <h3>📋 Important Information:</h3>
            <ul>
                <li><strong>Access Until:</strong> You'll continue to have access to premium features until {{ $user->subscription_ends_at ? $user->subscription_ends_at->format('F j, Y') : now()->format('F j, Y') }}</li>
                <li><strong>No Future Charges:</strong> You will not be charged for any future billing cycles</li>
                <li><strong>Data Retention:</strong> Your account and data will remain active</li>
                <li><strong>Downgrade:</strong> After the service end date, your account will be downgraded to the Basic plan</li>
            </ul>
        </div>
        
        <h3>What happens next?</h3>
        <ul>
            <li>Continue using premium features until {{ $user->subscription_ends_at ? $user->subscription_ends_at->format('F j, Y') : now()->format('F j, Y') }}</li>
            <li>After this date, you'll have access to Basic plan features</li>
            <li>Your products and data will be preserved</li>
            <li>You can resubscribe anytime to regain premium access</li>
        </ul>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ config('app.frontend_url') }}/dashboard" class="btn">Access Dashboard</a>
            <a href="{{ config('app.frontend_url') }}/settings/billing" class="btn btn-secondary">Resubscribe</a>
        </div>
        
        <p><strong>We'd love your feedback!</strong></p>
        <p>Your opinion matters to us. If you have a moment, we'd appreciate any feedback about your experience or suggestions for improvement. This helps us serve our community better.</p>
        
        <p>If you cancelled by mistake or would like to reactivate your subscription, you can do so anytime from your account settings.</p>
        
        <p>Thank you for being part of the Food Management System community. We hope to serve you again in the future!</p>
        
        <p>Best regards,<br>
        <strong>Food Management System Team</strong></p>
    </div>
    
    <div class="footer">
        <p>This is an automated email. Please do not reply to this message.</p>
        <p>© {{ date('Y') }} Food Management System. All rights reserved.</p>
    </div>
</body>
</html>