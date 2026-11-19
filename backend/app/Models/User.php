<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Core\Entities\CoreUserDocument;
use Modules\Core\Entities\CoreUserProfile;
use Modules\Crm\Entities\CrmAccount;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'bale_chat_id',
        'password',
        'avatar_path',
        'username',
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
            'locked_until' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if (! $user->isDirty('password') || ! $user->exists) {
                return;
            }
            static $hasColumns = null;
            $hasColumns ??= \Illuminate\Support\Facades\Schema::hasColumn('users', 'must_change_password');
            if ($hasColumns) {
                $user->password_changed_at = now();
                // A new password chosen without an explicit flag clears a pending forced change.
                if (! $user->isDirty('must_change_password')) {
                    $user->must_change_password = false;
                }
            }
        });
    }

    public function isLocked(): bool
    {
        $until = $this->locked_until ?? null;

        return $until !== null && $until->isFuture();
    }

    public function profile(): HasOne
    {
        return $this->hasOne(CoreUserProfile::class);
    }

    public function personalDocuments(): HasMany
    {
        return $this->hasMany(CoreUserDocument::class);
    }

    public function crmAccounts(): BelongsToMany
    {
        return $this->belongsToMany(CrmAccount::class, 'crm_account_users', 'user_id', 'account_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}

