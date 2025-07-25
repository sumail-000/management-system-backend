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

class AccountDeletionRequestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $reason;
    public $scheduledDate;
    public $confirmationToken;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, ?string $reason = null, ?string $scheduledDate = null, ?string $confirmationToken = null)
    {
        $this->user = $user;
        $this->reason = $reason;
        $this->scheduledDate = $scheduledDate;
        $this->confirmationToken = $confirmationToken;
        
        Log::info('Account deletion request email instance created', [
            'user_id' => $user->id,
            'email' => $user->email,
            'reason' => $reason,
            'scheduled_date' => $scheduledDate
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Account Deletion Request - Food Management System',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.account-deletion-request',
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