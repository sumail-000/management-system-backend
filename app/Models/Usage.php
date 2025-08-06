<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Usage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'month',
        'products',
        'qr_codes',
        'labels',
    ];

    protected $casts = [
        'products' => 'integer',
        'qr_codes' => 'integer',
        'labels' => 'integer',
    ];

    /**
     * Get the user that owns the usage record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}