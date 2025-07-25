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

class AccountDeletionConfirmationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $deletionDate;
    public $dataExported;
    public $exportedData;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, ?string $deletionDate = null, bool $dataExported = false, array $exportedData = [])
    {
        $this->user = $user;
        $this->deletionDate = $deletionDate ?? now()->addDay()->format('F j, Y \\a\\t g:i A T');
        $this->dataExported = $dataExported;
        $this->exportedData = $exportedData;
        
        Log::info('Account deletion confirmation email instance created', [
            'user_id' => $user->id,
            'email' => $user->email,
            'deletion_date' => $this->deletionDate,
            'data_exported' => $dataExported
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Final Notice: Account Deletion in 24 Hours - Food Management System',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.account-deletion-confirmation',
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