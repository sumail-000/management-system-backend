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

class SubscriptionCancellationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $plan;
    public $reason;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, $plan = null, $reason = null)
    {
        $this->user = $user;
        $this->plan = $plan ?: $user->membershipPlan;
        $this->reason = $reason ?: 'Unknown reason';
        
        Log::info('Subscription cancellation email instance created', [
            'user_id' => $user->id,
            'email' => $user->email,
            'plan' => $this->plan->name ?? 'Unknown',
            'reason' => $reason
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Subscription Cancelled - Food Management System',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.subscription-cancellation',
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