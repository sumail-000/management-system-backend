<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Code</title>
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
        .otp-code {
            background-color: #f8f9fa;
            border: 2px solid #007bff;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .otp-number {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
            letter-spacing: 8px;
            margin: 10px 0;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 0 0 8px 8px;
            font-size: 14px;
            color: #6c757d;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0; color: #007bff;">Food Management System</h1>
        <p style="margin: 10px 0 0 0; color: #6c757d;">Password Reset Request</p>
    </div>
    
    <div class="content">
        <h2>Password Reset Code</h2>
        <p>Hello,</p>
        <p>You have requested to reset your password for your Food Management System account. Please use the verification code below to proceed with resetting your password.</p>
        
        <div class="otp-code">
            <p style="margin: 0; font-size: 16px; color: #6c757d;">Your verification code is:</p>
            <div class="otp-number">{{ $otp }}</div>
            <p style="margin: 0; font-size: 14px; color: #6c757d;">This code will expire in 10 minutes</p>
        </div>
        
        <div class="warning">
            <strong>Security Notice:</strong>
            <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                <li>This code is valid for 10 minutes only</li>
                <li>Do not share this code with anyone</li>
                <li>If you didn't request this reset, please ignore this email</li>
                <li>Your password will remain unchanged until you complete the reset process</li>
            </ul>
        </div>
        
        <p>If you're having trouble with the password reset process, please contact our support team.</p>
        
        <p>Best regards,<br>
        <strong>Food Management System Team</strong></p>
    </div>
    
    <div class="footer">
        <p>This is an automated email. Please do not reply to this message.</p>
        <p>© {{ date('Y') }} Food Management System. All rights reserved.</p>
    </div>
</body>
</html>