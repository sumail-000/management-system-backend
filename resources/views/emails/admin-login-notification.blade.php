<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login Notification</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-success {
            background-color: #d4edda;
            color: #155724;
        }
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-blocked {
            background-color: #fff3cd;
            color: #856404;
        }
        .content {
            margin-bottom: 30px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: #f8f9fa;
            border-radius: 6px;
            overflow: hidden;
        }
        .details-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #dee2e6;
        }
        .details-table td:first-child {
            font-weight: 600;
            color: #495057;
            width: 30%;
        }
        .details-table tr:last-child td {
            border-bottom: none;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            color: #6c757d;
            font-size: 14px;
        }
        .security-note {
            background-color: #e7f3ff;
            border-left: 4px solid #0066cc;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .security-note h4 {
            margin: 0 0 8px 0;
            color: #0066cc;
            font-size: 14px;
        }
        .security-note p {
            margin: 0;
            font-size: 13px;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🛡️ Food Management System</div>
            <h2 style="margin: 0; color: #2c3e50;">Admin Login Notification</h2>
        </div>

        <div class="content">
            <p>Hello <strong>{{ $admin->name }}</strong>,</p>
            
            <p>We detected a login attempt to your admin account. Here are the details:</p>

            <table class="details-table">
                <tr>
                    <td>Status</td>
                    <td>
                        <span class="status-badge status-{{ $status }}">
                            {{ ucfirst($status) }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td>Email</td>
                    <td>{{ $admin->email }}</td>
                </tr>
                <tr>
                    <td>Date & Time</td>
                    <td>{{ $timestamp }}</td>
                </tr>
                <tr>
                    <td>IP Address</td>
                    <td>{{ $ipAddress }}</td>
                </tr>
                <tr>
                    <td>Location</td>
                    <td>{{ $location ?? 'Unknown' }}</td>
                </tr>
                <tr>
                    <td>Device</td>
                    <td>{{ $userAgent }}</td>
                </tr>
                @if($reason)
                <tr>
                    <td>Reason</td>
                    <td>{{ $reason }}</td>
                </tr>
                @endif
            </table>

            @if($status === 'success')
                <div class="security-note">
                    <h4>✅ Successful Login</h4>
                    <p>If this was you, no action is needed. If you didn't attempt to log in, please secure your account immediately.</p>
                </div>
            @elseif($status === 'failed')
                <div class="security-note">
                    <h4>⚠️ Failed Login Attempt</h4>
                    <p>Someone attempted to access your admin account with incorrect credentials. If this wasn't you, your account may be under attack.</p>
                </div>
            @elseif($status === 'blocked')
                <div class="security-note">
                    <h4>🚫 Blocked Login Attempt</h4>
                    <p>A login attempt was blocked due to IP address restrictions. If this was you, please contact your system administrator.</p>
                </div>
            @endif
        </div>

        <div class="footer">
            <p>This is an automated security notification from Food Management System.</p>
            <p>If you have any concerns, please contact your system administrator immediately.</p>
            <p style="margin-top: 15px; font-size: 12px; color: #868e96;">
                This email was sent on {{ now()->format('F j, Y \a\t g:i A T') }}
            </p>
        </div>
    </div>
</body>
</html>