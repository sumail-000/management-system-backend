<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QrCode extends Model
{
    use HasFactory;

    protected $table = 'qr_codes';

    protected $fillable = [
        'product_id',
        'url_slug',
        'image_path',
        'scan_count',
        'last_scanned_at',
    ];

    protected $casts = [
        'scan_count' => 'integer',
        'last_scanned_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }
}