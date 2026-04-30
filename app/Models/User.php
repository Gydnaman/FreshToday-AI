<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationship definitions
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function userPreferences()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function dailyMenus()
    {
        return $this->hasMany(DailyMenu::class);
    }

    public function userSubscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }
}
