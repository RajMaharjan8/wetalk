<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomPage extends Model
{
    /**
     * Slugs that would collide with existing application routes and so can never
     * be used for a custom page (the public route is a root-level catch-all).
     *
     * @var list<string>
     */
    public const RESERVED_SLUGS = [
        'admin', 'auth', 'login', 'register', 'logout', 'verify-otp',
        'forgot-password', 'dashboard', 'reports', 'samples', 'locale',
        'landing', 'check', 'up', 'p', 'api', 'storage', 'home',
    ];

    protected $fillable = [
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'published',
        'show_in_footer',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'show_in_footer' => 'boolean',
        ];
    }

    /** Resolve published pages by slug for the public route. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @param  Builder<CustomPage>  $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    /** Whether the given slug is reserved by an application route. */
    public static function isReservedSlug(string $slug): bool
    {
        return in_array(strtolower(trim($slug)), self::RESERVED_SLUGS, true);
    }

    /** The page's public URL. */
    public function url(): string
    {
        return url('/'.$this->slug);
    }
}
