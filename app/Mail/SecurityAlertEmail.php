<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class SecurityAlertEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $alertType;
    public $details;
    public $ipAddress;
    public $userAgent;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $alertType, array $details = [], ?string $ipAddress = null, ?string $userAgent = null)
    {
        $this->user = $user;
        $this->alertType = $alertType;
        $this->details = $details;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        
        Log::info('Security alert email instance created', [
            'user_id' => $user->id,
            'email' => $user->email,
            'alert_type' => $alertType,
            'ip_address' => $ipAddress
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subjects = [
            'password_reset' => 'Security Alert: Password Reset Completed',
            'cancellation_request' => 'Security Alert: Subscription Cancellation Requested',
            'account_deletion_request' => 'Security Alert: Account Deletion Requested',
            'suspicious_activity' => 'Security Alert: Suspicious Activity Detected',
            'login_from_new_device' => 'Security Alert: Login from New Device'
        ];

        return new Envelope(
            subject: $subjects[$this->alertType] ?? 'Security Alert - Food Management System',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.security-alert',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}