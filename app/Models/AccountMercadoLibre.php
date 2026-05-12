<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountMercadoLibre extends Model
{
    use HasFactory;

    protected $table = 'account_mercado_libre';

    protected $fillable = [
        'user_id',
        'ml_user_id',
        'access_token',
        'refresh_token',
        'live_mode',
        'expires_at',
        'public_key',
        'scope',
        'token_type'
    ];

    protected $casts = [
        'live_mode' => 'boolean',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
        'public_key'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Verifica si el token está expirado o por expirar (margen de 5 min)
     */
    public function isTokenExpired(): bool
    {
        return $this->expires_at && $this->expires_at->subMinutes(5)->isPast();
    }
}
