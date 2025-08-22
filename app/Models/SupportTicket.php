<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number', 'user_id', 'category', 'priority', 'subject', 'status', 'last_reply_at'
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function messages(): HasMany { return $this->hasMany(SupportMessage::class, 'ticket_id'); }

    /**
     * Generate next ticket number in the format TK-YYYY-XXX
     * Uses a transaction and FOR UPDATE lock to avoid gaps and race conditions.
     */
    public static function generateTicketNumber(): string
    {
        return DB::transaction(function () {
            $year = Carbon::now()->format('Y');

            // Lock the table rows for this year
            $last = DB::table('support_tickets')
                ->where('ticket_number', 'like', 'TK-' . $year . '-%')
                ->select('ticket_number')
                ->orderByDesc('ticket_number')
                ->lockForUpdate()
                ->first();

            $nextSeq = 1;
            if ($last) {
                // ticket_number like TK-2024-001 => extract last 3 digits
                $parts = explode('-', $last->ticket_number);
                $seq = (int)($parts[2] ?? 0);
                $nextSeq = $seq + 1;
            }

            $seqStr = str_pad((string)$nextSeq, 3, '0', STR_PAD_LEFT);
            return 'TK-' . $year . '-' . $seqStr;
        });
    }
}
