<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id', 'user_id', 'admin_id', 'is_admin', 'message'
    ];

    public function ticket(): BelongsTo { return $this->belongsTo(SupportTicket::class, 'ticket_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function admin(): BelongsTo { return $this->belongsTo(Admin::class); }
}
