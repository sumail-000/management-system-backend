<?php

namespace App\Mail;

use App\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminLoginNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $admin;
    public $status;
    public $ipAddress;
    public $userAgent;
    public $timestamp;
    public $location;
    public $reason;

    /**
     * Create a new message instance.
     */
    public function __construct(
        Admin $admin,
        string $status,
        string $ipAddress,
        string $userAgent,
        string $timestamp,
        ?string $location = null,
        ?string $reason = null
    ) {
        $this->admin = $admin;
        $this->status = $status;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->timestamp = $timestamp;
        $this->location = $location;
        $this->reason = $reason;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match($this->status) {
            'success' => 'Admin Login Successful - ' . config('app.name'),
            'failed' => 'Failed Admin Login Attempt - ' . config('app.name'),
            'blocked' => 'Blocked Admin Login Attempt - ' . config('app.name'),
            default => 'Admin Login Notification - ' . config('app.name')
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-login-notification',
            with: [
                'admin' => $this->admin,
                'status' => $this->status,
                'ipAddress' => $this->ipAddress,
                'userAgent' => $this->userAgent,
                'timestamp' => $this->timestamp,
                'location' => $this->location,
                'reason' => $this->reason,
            ]
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