<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountMercadoPago extends Model
{
    use HasFactory;

    protected $table = 'account_mercado_pago';

    protected $fillable = [
        'user_id',
        'mp_user_id',
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

    /**
     * Relación con el usuario de la aplicación
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
