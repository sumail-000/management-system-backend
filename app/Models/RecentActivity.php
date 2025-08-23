<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecentActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'user_id',
        'product_id',
        'plan_name',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function logSignup(User $user, string $planName): self
    {
        return self::create([
            'type' => 'user_signup',
            'user_id' => $user->id,
            'plan_name' => $planName,
        ]);
    }

    public static function logPlanUpgrade(User $user, string $planName): self
    {
        return self::create([
            'type' => 'plan_upgraded',
            'user_id' => $user->id,
            'plan_name' => $planName,
        ]);
    }

    public static function logProductCreated(User $user, Product $product): self
    {
        return self::create([
            'type' => 'product_created',
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public static function logProductFlagged(Product $product): self
    {
        return self::create([
            'type' => 'product_flagged',
            'user_id' => $product->user_id,
            'product_id' => $product->id,
        ]);
    }

    public static function latestItems(int $limit = 5)
    {
        return self::with(['user:id,name', 'product:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
