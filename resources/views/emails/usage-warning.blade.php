<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usage Warning</title>
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
        .warning-box {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .usage-meter {
            background-color: #e9ecef;
            border-radius: 10px;
            height: 20px;
            margin: 20px 0;
            overflow: hidden;
        }
        .usage-fill {
            background: linear-gradient(90deg, #28a745 0%, #ffc107 70%, #dc3545 100%);
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
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
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-success {
            background-color: #28a745;
        }
        .tips-box {
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
        <p style="margin: 10px 0 0 0; color: #6c757d;">Usage Alert</p>
    </div>
    
    <div class="content">
        <div class="warning-box">
            <h2 style="color: #856404; margin: 0;">⚠️ Usage Warning</h2>
            <p style="margin: 10px 0 0 0; font-size: 18px; font-weight: bold;">
                You've used {{ $usagePercentage }}% of your {{ ucfirst($usageType) }} limit
            </p>
        </div>
        
        <p>Hello {{ $user->name }},</p>
        
        <p>We wanted to let you know that you're approaching your plan limits. You've currently used <strong>{{ $usagePercentage }}%</strong> of your {{ $plan ? $plan->name : 'current' }} plan's {{ $usageType }} allowance.</p>
        
        <div class="usage-meter">
            <div class="usage-fill" style="width: {{ $usagePercentage }}%;"></div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3 style="margin: 0 0 10px 0; color: #007bff;">Current Usage</h3>
                <p style="margin: 0; font-size: 24px; font-weight: bold; color: #ffc107;">{{ number_format($currentUsage) }}</p>
                <p style="margin: 5px 0 0 0; color: #6c757d;">{{ ucfirst($usageType) }}</p>
            </div>
            <div class="stat-card">
                <h3 style="margin: 0 0 10px 0; color: #007bff;">Plan Limit</h3>
                <p style="margin: 0; font-size: 24px; font-weight: bold; color: #28a745;">{{ number_format($limit) }}</p>
                <p style="margin: 5px 0 0 0; color: #6c757d;">{{ ucfirst($usageType) }}</p>
            </div>
        </div>
        
        @if($plan)
        <div class="stat-card" style="margin: 20px 0;">
            <h3 style="margin: 0 0 10px 0; color: #007bff;">Current Plan</h3>
            <p style="margin: 0; font-size: 18px; font-weight: bold;">{{ $plan->name }}</p>
            @if($plan->price > 0)
            <p style="margin: 5px 0 0 0; color: #6c757d;">${{ number_format($plan->price, 2) }}/month</p>
            @endif
        </div>
        @endif
        
        <h3>What happens when you reach 100%?</h3>
        <ul>
            <li>Your account will be temporarily limited</li>
            <li>You won't be able to add new {{ $usageType }}</li>
            <li>Existing data will remain accessible</li>
            <li>You can upgrade your plan to continue adding {{ $usageType }}</li>
        </ul>
        
        <div style="text-align: center; margin: 30px 0;">
            @if($plan && $plan->name !== 'Premium')
            <a href="{{ config('app.frontend_url') }}/subscription/upgrade" class="btn btn-success">Upgrade Plan</a>
            @endif
            <a href="{{ config('app.frontend_url') }}/dashboard" class="btn">View Dashboard</a>
            <a href="{{ config('app.frontend_url') }}/settings/usage" class="btn btn-warning">Manage Usage</a>
        </div>
        
        <div class="tips-box">
            <h3>💡 Tips to Optimize Your Usage:</h3>
            <ul>
                @switch($usageType)
                    @case('products')
                        <li>Remove duplicate or outdated products</li>
                        <li>Archive products you no longer use</li>
                        <li>Combine similar product variants</li>
                        <li>Use categories to better organize products</li>
                        @break
                    @case('storage')
                        <li>Delete unnecessary files and images</li>
                        <li>Compress large images before uploading</li>
                        <li>Remove old backup files</li>
                        <li>Clean up temporary data</li>
                        @break
                    @case('orders')
                        <li>Archive completed orders from previous months</li>
                        <li>Remove test or cancelled orders</li>
                        <li>Export old data for external storage</li>
                        @break
                    @default
                        <li>Review and clean up unused data</li>
                        <li>Archive old records</li>
                        <li>Optimize your data usage</li>
                @endswitch
            </ul>
        </div>
        
        @if($plan && $plan->name !== 'Premium')
        <h3>🚀 Upgrade Benefits:</h3>
        <p>Upgrading your plan will give you:</p>
        <ul>
            <li>Higher {{ $usageType }} limits</li>
            <li>Priority customer support</li>
            <li>Advanced features and analytics</li>
            <li>No usage interruptions</li>
            @if($plan->name === 'Basic')
            <li>Access to premium integrations</li>
            <li>Advanced reporting tools</li>
            @endif
        </ul>
        @endif
        
        <p>If you have any questions about your usage or need help optimizing your account, our support team is here to help.</p>
        
        <p>Best regards,<br>
        <strong>Food Management System Team</strong></p>
    </div>
    
    <div class="footer">
        <p>This is an automated usage alert. You can manage your notification preferences in your account settings.</p>
        <p>© {{ date('Y') }} Food Management System. All rights reserved.</p>
    </div>
</body>
</html>