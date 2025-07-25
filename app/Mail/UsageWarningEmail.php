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

class UsageWarningEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $usagePercentage;
    public $usageType;
    public $currentUsage;
    public $limit;
    public $plan;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, int $usagePercentage, string $usageType, int $currentUsage, int $limit)
    {
        $this->user = $user;
        $this->usagePercentage = $usagePercentage;
        $this->usageType = $usageType;
        $this->currentUsage = $currentUsage;
        $this->limit = $limit;
        $this->plan = $user->membershipPlan;
        
        Log::info('Usage warning email instance created', [
            'user_id' => $user->id,
            'email' => $user->email,
            'usage_percentage' => $usagePercentage,
            'usage_type' => $usageType,
            'current_usage' => $currentUsage,
            'limit' => $limit
        ]);
    }

    /**
     * Get the message envelope.
          */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Usage Alert: ' . $this->usagePercentage . '% of Your Plan Limit Reached',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.usage-warning',
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