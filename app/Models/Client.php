<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Client extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'password',
        'price_list_id',
        // No listamos los campos de verificación aquí, se manejan internamente
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verified_at',
        'email_verification_expires_at',
        'email_verification_code',
        'price_list_id'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'email_verification_expires_at' => 'datetime',
    ];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    // Método para marcar como verificado (usa el trait MustVerifyEmail)
    public function markEmailAsVerified()
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
            'email_verification_code' => null, // Limpiar el código usado
            'email_verification_expires_at' => null, // Limpiar la expiración
        ])->save();
    }

    // Los pedidos del cliente
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
