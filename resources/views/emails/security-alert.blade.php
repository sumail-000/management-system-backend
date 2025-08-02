<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Alert</title>
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
        .alert-box {
            background-color: #f8d7da;
            border: 2px solid #dc3545;
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
            background-color: #dc3545;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn-primary {
            background-color: #007bff;
        }
        .security-tips {
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
        <p style="margin: 10px 0 0 0; color: #6c757d;">Security Alert</p>
    </div>
    
    <div class="content">
        <div class="alert-box">
            <h2 style="color: #721c24; margin: 0;">🔒 Security Alert</h2>
            <p style="margin: 10px 0 0 0;">
                @switch($alertType)
                    @case('password_reset')
                        Your password has been successfully reset
                        @break
                    @case('cancellation_request')
                        Subscription cancellation has been requested
                        @break
                    @case('account_deletion_request')
                        Account deletion has been requested
                        @break
                    @case('suspicious_activity')
                        Suspicious activity detected on your account
                        @break
                    @case('login_from_new_device')
                        Login detected from a new device
                        @break
                    @default
                        Security event detected on your account
                @endswitch
            </p>
        </div>
        
        <p>Hello {{ $user->name }},</p>
        
        @switch($alertType)
            @case('password_reset')
                <p>This email confirms that your password for Food Management System has been successfully changed.</p>
                <p><strong>If you made this change:</strong> No further action is required.</p>
                <p><strong>If you did NOT make this change:</strong> Your account may have been compromised. Please contact our support team immediately.</p>
                @break
                
            @case('cancellation_request')
                <p>A request to cancel your subscription has been submitted from your account.</p>
                <p><strong>If you made this request:</strong> Your cancellation is being processed.</p>
                <p><strong>If you did NOT make this request:</strong> Please contact our support team immediately to secure your account.</p>
                @break
                
            @case('account_deletion_request')
                <p>A request to delete your account has been submitted. This is a serious security event that requires your attention.</p>
                <p><strong>If you made this request:</strong> Your account deletion is being processed.</p>
                <p><strong>If you did NOT make this request:</strong> Please contact our support team immediately to prevent account deletion.</p>
                @break
                
            @case('suspicious_activity')
                <p>We've detected unusual activity on your account that may indicate unauthorized access.</p>
                <p>Please review your recent account activity and change your password if necessary.</p>
                @break
                
            @case('login_from_new_device')
                <p>We detected a login to your account from a device we haven't seen before.</p>
                <p><strong>If this was you:</strong> No action needed, but consider enabling two-factor authentication.</p>
                <p><strong>If this wasn't you:</strong> Please change your password immediately.</p>
                @break
        @endswitch
        
        <div class="details-box">
            <h3>Event Details:</h3>
            <ul>
                <li><strong>Date & Time:</strong> {{ now()->format('F j, Y \\a\\t g:i A T') }}</li>
                @if($ipAddress)
                <li><strong>IP Address:</strong> {{ $ipAddress }}</li>
                @endif
                @if($userAgent)
                <li><strong>Device/Browser:</strong> {{ $userAgent }}</li>
                @endif
                @if(!empty($details))
                    @foreach($details as $key => $value)
                    <li><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</li>
                    @endforeach
                @endif
            </ul>
        </div>
        
        @if(in_array($alertType, ['password_reset', 'suspicious_activity', 'login_from_new_device']))
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ config('app.frontend_url') }}/settings/security" class="btn btn-primary">Review Security Settings</a>
            @if($alertType !== 'password_reset')
            <a href="{{ config('app.frontend_url') }}/auth/change-password" class="btn">Change Password</a>
            @endif
        </div>
        @endif
        
        @if(in_array($alertType, ['cancellation_request', 'account_deletion_request']))
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ config('app.frontend_url') }}/settings/account" class="btn btn-primary">Manage Account</a>
            <a href="{{ config('app.frontend_url') }}/support" class="btn">Contact Support</a>
        </div>
        @endif
        
        <div class="security-tips">
            <h3>🛡️ Security Best Practices:</h3>
            <ul>
                <li>Use a strong, unique password for your account</li>
                <li>Enable two-factor authentication when available</li>
                <li>Never share your login credentials with anyone</li>
                <li>Log out from shared or public computers</li>
                <li>Regularly review your account activity</li>
                <li>Keep your contact information up to date</li>
            </ul>
        </div>
        
        <p><strong>Need Help?</strong></p>
        <p>If you have any concerns about your account security or need assistance, please contact our support team immediately. We're here to help keep your account safe.</p>
        
        <p>Best regards,<br>
        <strong>Food Management System Security Team</strong></p>
    </div>
    
    <div class="footer">
        <p>This is an automated security alert. Please do not reply to this message.</p>
        <p>© {{ date('Y') }} Food Management System. All rights reserved.</p>
    </div>
</body>
</html>