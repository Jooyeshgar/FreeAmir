<?php

namespace App\Models;

use App\Notifications\UserVerificationNotification;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasRoles, Impersonate, MustVerifyEmailTrait, Notifiable;

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
        return ! is_null($this->email_verified_at);
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new UserVerificationNotification);
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    public function canImpersonate(): bool
    {
        return $this->can('users.impersonate') || $this->can('users.*');
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->can('access-super-admin-panel');
    }

    public function canImpersonateUser(User $user): bool
    {
        if (! $this->canImpersonate() || $this->is($user) || ! $user->canBeImpersonated()) {
            return false;
        }

        if ($this->hasApplicationRole('Super-Admin')) {
            return true;
        }

        if ($user->hasApplicationRole('Admin')) {
            return false;
        }

        if ($this->hasApplicationRole('Admin')) {
            return true;
        }

        if (! $this->hasApplicationRole('Accountant')) {
            return false;
        }

        if ($user->hasAnyApplicationRole(['Super-Admin', 'Admin', 'Accountant'])) {
            return false;
        }

        return $user->hasAnyApplicationRole(['Warehousekeeper', 'Seller', 'Employee']);
    }

    public function hasApplicationRole(string $role): bool
    {
        return $this->hasAnyRole($this->applicationRoleNames($role));
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function hasAnyApplicationRole(array $roles): bool
    {
        return collect($roles)->contains(fn (string $role) => $this->hasApplicationRole($role));
    }

    /**
     * Role names are currently localized by RolesAndPermissionsSeeder, so
     * recognize both supported locales as well as the canonical name.
     *
     * @return array<int, string>
     */
    private function applicationRoleNames(string $role): array
    {
        return collect(['en', 'fa'])->map(fn (string $locale) => trans($role, [], $locale))
            ->push($role)->unique()->values()->all();
    }
}
