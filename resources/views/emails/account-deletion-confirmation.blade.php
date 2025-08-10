<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Account Deletion Notice</title>
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
        .critical-box {
            background-color: #f8d7da;
            border: 3px solid #dc3545;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            margin: 20px 0;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
        .countdown-box {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .info-box {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .success-box {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            color: #155724;
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
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
            font-weight: bold;
            font-size: 16px;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-success {
            background-color: #28a745;
        }
        .btn-primary {
            background-color: #007bff;
        }
        .data-summary {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        .last-chance {
            border: 2px dashed #ffc107;
            background-color: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0; color: #dc3545;">Food Management System</h1>
        <p style="margin: 10px 0 0 0; color: #6c757d; font-weight: bold;">FINAL DELETION NOTICE</p>
    </div>
    
    <div class="content">
        <div class="critical-box">
            <h2 style="color: #721c24; margin: 0; font-size: 24px;">🚨 URGENT: Account Deletion in 24 Hours</h2>
            <p style="margin: 15px 0 0 0; font-size: 18px; font-weight: bold;">
                This is your final notice before permanent account deletion
            </p>
        </div>
        
        <p>Hello {{ $user->name }},</p>
        
        <p><strong>This is your final warning.</strong> Your Food Management System account is scheduled for permanent deletion in approximately 24 hours.</p>
        
        <div class="countdown-box">
            <h3 style="margin: 0 0 10px 0; font-size: 20px;">⏰ Deletion Scheduled For:</h3>
            <p style="margin: 0; font-size: 24px; font-weight: bold;">{{ $deletionDate }}</p>
        </div>
        
        <div class="last-chance">
            <h3 style="margin: 0 0 15px 0; color: #856404;">🛑 LAST CHANCE TO CANCEL</h3>
            <p style="margin: 0; font-size: 16px;">If you want to keep your account, you can cancel the deletion request by logging into your account before the scheduled time.</p>
        </div>
        
        <h3>📊 Account Summary</h3>
        <div class="info-box">
            <h4 style="margin: 0 0 15px 0; color: #0c5460;">Account Information:</h4>
            <ul style="margin: 0; padding-left: 20px;">
                <li><strong>Email:</strong> {{ $user->email }}</li>
                <li><strong>Member Since:</strong> {{ $user->created_at->format('F j, Y') }}</li>
                <li><strong>Current Plan:</strong> {{ $user->membershipPlan ? $user->membershipPlan->name : 'Free' }}</li>
                <li><strong>Deletion Request Date:</strong> {{ now()->subDays(6)->format('F j, Y') }}</li>
            </ul>
        </div>
        
        
        <h3>🗑️ What Will Be Deleted</h3>
        <div class="data-summary">
            <h4 style="margin: 0 0 15px 0; color: #dc3545;">The following will be permanently removed:</h4>
            <ul style="margin: 0; padding-left: 20px;">
                <li><strong>Account & Profile:</strong> All personal information and settings</li>
                <li><strong>Products & Inventory:</strong> All product data and stock information</li>
                <li><strong>Orders & Transactions:</strong> Complete order history and payment records</li>
                <li><strong>Files & Images:</strong> All uploaded files, photos, and documents</li>
                <li><strong>Reports & Analytics:</strong> All generated reports and analytics data</li>
                <li><strong>Subscription Data:</strong> Billing history and subscription information</li>
                <li><strong>Custom Settings:</strong> All preferences and customizations</li>
            </ul>
        </div>
        
        <div class="critical-box">
            <h3 style="color: #721c24; margin: 0 0 15px 0;">⚠️ IMPORTANT REMINDERS</h3>
            <ul style="margin: 0; padding-left: 20px; text-align: left;">
                <li><strong>This action is IRREVERSIBLE</strong> - Once deleted, your account cannot be recovered</li>
                <li><strong>All data will be permanently lost</strong> - No backups will be retained</li>
                <li><strong>Active subscriptions will be cancelled</strong> - No refunds for unused time</li>
                <li><strong>You will lose access immediately</strong> - Cannot login after deletion</li>
                <li><strong>Email address will be freed</strong> - Can be used for new account registration</li>
            </ul>
        </div>
        
        <h3>🤔 Changed Your Mind?</h3>
        <p>If you're having second thoughts or if there's something we can do to improve your experience, we'd love to help. Our support team is standing by to assist you.</p>
        
        <p>You can cancel this deletion by logging into your account before the scheduled time.</p>
        
        <h3>🔒 Security Notice</h3>
        <p>If you did not request this account deletion, please contact our security team immediately. This could indicate unauthorized access to your account.</p>
        
        <div style="text-align: center; margin: 20px 0;">
            <a href="{{ config('app.frontend_url') }}/support/security" class="btn btn-danger">Report Unauthorized Access</a>
        </div>
        
        <div class="info-box">
            <h4 style="margin: 0 0 15px 0; color: #0c5460;">📞 Need Help?</h4>
            <p style="margin: 0;">Our support team is available 24/7 to help with any questions or concerns about your account deletion.</p>
            <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                <li>Email: support@foodmanagementsystem.com</li>
                <li>Live Chat: Available on our website</li>
                <li>Phone: 1-800-FOOD-MGT (1-800-366-3648)</li>
            </ul>
        </div>
        
        <p>We're truly sorry to see you go. Thank you for being part of the Food Management System community.</p>
        
        <p>Best regards,<br>
        <strong>Food Management System Team</strong></p>
    </div>
    
    <div class="footer">
        <p><strong>This is your final notice.</strong> After {{ $deletionDate }}, this email address will no longer be associated with our system.</p>
        <p>© {{ date('Y') }} Food Management System. All rights reserved.</p>
    </div>
</body>
</html>