<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class TeamMember extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'password',
        'role',
        'permissions',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'permissions' => 'array',
        'last_login_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getEffectivePermissionsAttribute()
    {
        // Inherit enterprise owner's plan permissions; team member-specific overrides can be added here.
        $owner = $this->owner;
        $base = [
            'plan' => $owner?->membershipPlan?->name,
            'can_create_products' => true,
            'can_manage_labels' => true,
            'can_manage_qr' => true,
        ];
        return array_merge($base, $this->permissions ?? []);
    }
}
