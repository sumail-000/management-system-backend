<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PasswordResetOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $userEmail;

    /**
     * Create a new message instance.
     */
    public function __construct(string $otp, string $userEmail)
    {
        $this->otp = $otp;
        $this->userEmail = $userEmail;
        
        Log::info('Password reset OTP email instance created', [
            'email' => $userEmail,
            'otp_length' => strlen($otp)
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Password Reset Code - Food Management System',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.password-reset-otp',
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