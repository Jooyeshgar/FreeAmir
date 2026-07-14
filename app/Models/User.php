<?php

namespace App\Models;

use App\Notifications\UserVerificationNotification;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasRoles, MustVerifyEmailTrait, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Determine if the user has verified their email address.
     */
    public function hasVerifiedEmail(): bool
    {
        if (! config('app.email_verification')) {
            return true;
        }

        return ! is_null($this->email_verified_at);
    }

    /**
     * Send the email verification notification when verification is required.
     */
    public function sendEmailVerificationNotification(): void
    {
        if (config('app.email_verification')) {
            $this->notify(new UserVerificationNotification);
        }
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }
}
