<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Food Management System</title>
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
        .welcome-box {
            background-color: #d4edda;
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .features {
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
        ul {
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0; color: #007bff;">Food Management System</h1>
        <p style="margin: 10px 0 0 0; color: #6c757d;">Welcome Aboard!</p>
    </div>
    
    <div class="content">
        <div class="welcome-box">
            <h2 style="color: #28a745; margin: 0;">🎉 Welcome {{ $user->name }}!</h2>
            <p style="margin: 10px 0 0 0;">Your account has been successfully created</p>
        </div>
        
        <p>Hello {{ $user->name }},</p>
        <p>Welcome to the Food Management System! We're excited to have you join our community of food industry professionals.</p>
        
        <div class="features">
            <h3>What you can do with your account:</h3>
            <ul>
                <li><strong>Product Management:</strong> Create and manage your food products</li>
                <li><strong>Nutritional Analysis:</strong> Get detailed nutritional information</li>
                <li><strong>Label Generation:</strong> Create professional food labels</li>
                <li><strong>QR Code Creation:</strong> Generate QR codes for your products</li>
                <li><strong>Ingredient Tracking:</strong> Manage and track ingredients</li>
                <li><strong>Compliance Tools:</strong> Ensure regulatory compliance</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ config('app.frontend_url') }}/dashboard" class="btn">Get Started Now</a>
        </div>
        
        <p><strong>Your Account Details:</strong></p>
        <ul>
            <li>Email: {{ $user->email }}</li>
            <li>Membership Plan: {{ $user->membershipPlan->name ?? 'Basic' }}</li>
            <li>Registration Date: {{ $user->created_at->format('F j, Y') }}</li>
        </ul>
        
        <p>If you have any questions or need assistance getting started, our support team is here to help. Simply reply to this email or contact us through the platform.</p>
        
        <p>Thank you for choosing Food Management System!</p>
        
        <p>Best regards,<br>
        <strong>Food Management System Team</strong></p>
    </div>
    
    <div class="footer">
        <p>This is an automated email. Please do not reply to this message.</p>
        <p>© {{ date('Y') }} Food Management System. All rights reserved.</p>
    </div>
</body>
</html>