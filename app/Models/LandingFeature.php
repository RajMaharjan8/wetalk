<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LandingFeature extends Model
{
    public const SECTIONS = ['features', 'steps', 'formats', 'samples', 'faqs'];

    protected $fillable = [
        'section',
        'title',
        'slug',
        'excerpt',
        'description',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'badge',
        'icon_path',
        'pdf_path',
        'link',
        'visible',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Auto-fill a unique slug for sample cards (used by their detail page)
        // when one isn't set explicitly.
        static::saving(function (LandingFeature $feature) {
            if ($feature->section === 'samples' && blank($feature->slug) && filled($feature->title)) {
                $base = Str::slug($feature->title) ?: 'sample';
                $slug = $base;
                $i = 2;

                while (static::where('slug', $slug)->where('id', '!=', $feature->id)->exists()) {
                    $slug = $base.'-'.$i++;
                }

                $feature->slug = $slug;
            }
        });

        // Remove the uploaded icon file when a card is deleted.
        static::deleting(function (LandingFeature $feature) {
            if ($feature->icon_path) {
                Storage::disk('public')->delete($feature->icon_path);
            }
        });
    }

    /** The public detail-page URL for a sample card, or null when no slug. */
    public function sampleUrl(): ?string
    {
        return $this->section === 'samples' && filled($this->slug)
            ? route('sample-pages.show', $this->slug)
            : null;
    }

    /** @param  Builder<LandingFeature>  $query */
    public function scopeSection(Builder $query, string $section): Builder
    {
        return $query->where('section', $section)->orderBy('order')->orderBy('id');
    }

    /**
     * Only cards marked visible — used when rendering the public landing page.
     *
     * @param  Builder<LandingFeature>  $query
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visible', true);
    }

    /** Public URL of the uploaded icon, or null when none is set. */
    public function iconUrl(): ?string
    {
        return $this->icon_path ? Storage::disk('public')->url($this->icon_path) : null;
    }

    /** Public URL of the uploaded PDF (samples only), or null when none is set. */
    public function pdfUrl(): ?string
    {
        return $this->pdf_path ? Storage::disk('public')->url($this->pdf_path) : null;
    }

    /** Fallback badge text (uppercased initials of the title) when no badge/icon is set. */
    public function badgeText(): string
    {
        if (filled($this->badge)) {
            return $this->badge;
        }

        return Str::of($this->title)->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('');
    }
}
