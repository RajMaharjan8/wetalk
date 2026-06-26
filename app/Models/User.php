<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'google_id', 'avatar_url', 'is_admin', 'suspended_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** Default per-user report cap, used when the admin hasn't set one. */
    public const MAX_REPORTS = 2;

    /**
     * The active per-user report limit: the admin-configured global setting
     * ("max_reports_per_user") when present, otherwise the default constant.
     */
    public static function reportLimit(): int
    {
        $limit = (int) Setting::get('max_reports_per_user', (string) self::MAX_REPORTS);

        return $limit > 0 ? $limit : self::MAX_REPORTS;
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function hasReachedReportLimit(): bool
    {
        return $this->reports()->count() >= self::reportLimit();
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

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
            'is_admin' => 'boolean',
            'suspended_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
