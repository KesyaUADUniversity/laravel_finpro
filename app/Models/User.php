<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role', // admin, kasir
        'phone',
        'address',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Relationships
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'cashier_id');
    }

    // Scope untuk role
    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeCashier($query)
    {
        return $query->where('role', 'kasir');
    }
}