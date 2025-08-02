<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Deletion Request</title>
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
        .danger-box {
            background-color: #f8d7da;
            border: 2px solid #dc3545;
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
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            color: #856404;
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
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
            font-weight: bold;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-primary {
            background-color: #007bff;
        }
        .timeline {
            border-left: 3px solid #007bff;
            padding-left: 20px;
            margin: 20px 0;
        }
        .timeline-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        .timeline-item:last-child {
            border-bottom: none;
        }
        .data-list {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0; color: #007bff;">Food Management System</h1>
        <p style="margin: 10px 0 0 0; color: #6c757d;">Account Deletion Request</p>
    </div>
    
    <div class="content">
        <div class="danger-box">
            <h2 style="color: #721c24; margin: 0;">⚠️ Account Deletion Requested</h2>
            <p style="margin: 10px 0 0 0; font-size: 16px;">
                Your account deletion request has been received and is being processed
            </p>
        </div>
        
        <p>Hello {{ $user->name }},</p>
        
        <p>We have received your request to delete your Food Management System account. This email confirms that your deletion request is being processed.</p>
        
        <div class="info-box">
            <h3 style="margin: 0 0 15px 0; color: #0c5460;">📋 Request Details:</h3>
            <ul style="margin: 0; padding-left: 20px;">
                <li><strong>Account Email:</strong> {{ $user->email }}</li>
                <li><strong>Request Date:</strong> {{ now()->format('F j, Y \\a\\t g:i A T') }}</li>
                @if($reason)
                <li><strong>Reason:</strong> {{ $reason }}</li>
                @endif
                @if($scheduledDate)
                <li><strong>Scheduled Deletion:</strong> {{ $scheduledDate }}</li>
                @endif
            </ul>
        </div>
        
        <h3>🕐 What Happens Next?</h3>
        <div class="timeline">
            <div class="timeline-item">
                <h4 style="margin: 0 0 5px 0; color: #007bff;">Step 1: Confirmation Period</h4>
                <p style="margin: 0;">You have 7 days to confirm or cancel this deletion request.</p>
            </div>
            <div class="timeline-item">
                <h4 style="margin: 0 0 5px 0; color: #007bff;">Step 2: Final Warning</h4>
                <p style="margin: 0;">24 hours before deletion, we'll send a final confirmation email.</p>
            </div>
            <div class="timeline-item">
                <h4 style="margin: 0 0 5px 0; color: #dc3545;">Step 3: Account Deletion</h4>
                <p style="margin: 0;">Your account and all associated data will be permanently deleted.</p>
            </div>
        </div>
        
        <div class="warning-box">
            <h3 style="margin: 0 0 15px 0;">⚠️ Important: What Will Be Deleted</h3>
            <div class="data-list">
                <h4 style="margin: 0 0 10px 0;">The following data will be permanently removed:</h4>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Your user profile and account information</li>
                    <li>All products and inventory data</li>
                    <li>Order history and transaction records</li>
                    <li>Uploaded files and images</li>
                    <li>Custom settings and preferences</li>
                    <li>Subscription and billing information</li>
                    <li>All associated reports and analytics</li>
                </ul>
            </div>
            <p style="margin: 15px 0 0 0; font-weight: bold; color: #721c24;">⚠️ This action cannot be undone. Please ensure you have backed up any important data.</p>
        </div>
        
        <h3>🔄 Want to Change Your Mind?</h3>
        <p>If you've changed your mind about deleting your account, you can cancel this request at any time during the confirmation period.</p>
        
        <div style="text-align: center; margin: 30px 0;">
            @if($confirmationToken)
            <a href="{{ config('app.frontend_url') }}/account/delete/confirm/{{ $confirmationToken }}" class="btn btn-danger">Confirm Deletion</a>
            <a href="{{ config('app.frontend_url') }}/account/delete/cancel/{{ $confirmationToken }}" class="btn btn-secondary">Cancel Request</a>
            @else
            <a href="{{ config('app.frontend_url') }}/settings/account" class="btn btn-primary">Manage Account</a>
            @endif
            <a href="{{ config('app.frontend_url') }}/support" class="btn btn-secondary">Contact Support</a>
        </div>
        
        <div class="info-box">
            <h3 style="margin: 0 0 15px 0; color: #0c5460;">💾 Before You Go - Data Export</h3>
            <p style="margin: 0;">If you need a copy of your data before deletion, you can export it from your account dashboard. This includes:</p>
            <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                <li>Product catalog and inventory data</li>
                <li>Order history and customer information</li>
                <li>Reports and analytics data</li>
                <li>Account settings and preferences</li>
            </ul>
            <div style="text-align: center; margin: 15px 0 0 0;">
                <a href="{{ config('app.frontend_url') }}/settings/export" class="btn btn-primary">Export My Data</a>
            </div>
        </div>
        
        <h3>🤝 We're Sorry to See You Go</h3>
        <p>We're sad to see you leave the Food Management System family. If there's anything we could have done better or if you're leaving due to a specific issue, we'd love to hear from you.</p>
        
        <p>Your feedback helps us improve our service for other users. Please consider reaching out to our support team if you'd like to share your experience.</p>
        
        <div class="warning-box">
            <h3 style="margin: 0 0 15px 0;">🔒 Security Notice</h3>
            <p style="margin: 0;">If you did not request this account deletion, please contact our support team immediately. This could indicate unauthorized access to your account.</p>
            <div style="text-align: center; margin: 15px 0 0 0;">
                <a href="{{ config('app.frontend_url') }}/support/security" class="btn btn-danger">Report Unauthorized Access</a>
            </div>
        </div>
        
        <p>Thank you for being part of our community.</p>
        
        <p>Best regards,<br>
        <strong>Food Management System Team</strong></p>
    </div>
    
    <div class="footer">
        <p>This is an automated message regarding your account deletion request.</p>
        <p>© {{ date('Y') }} Food Management System. All rights reserved.</p>
    </div>
</body>
</html>