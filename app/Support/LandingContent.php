<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Central registry for the editable, single-value content on the public landing
 * page (hero text, section headings, SEO meta and footer). The repeatable cards
 * live in the landing_features table; everything here is a one-off string stored
 * in the settings key/value table under the "landing_" prefix.
 */
class LandingContent
{
    /**
     * Every editable landing field keyed by its settings key, with the default
     * shown when the admin hasn't overridden it.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            // Brand
            'landing_site_name' => config('app.name'),

            // SEO / meta
            'landing_meta_title' => config('app.name').' — Final-year report & documentation generator',
            'landing_meta_description' => 'Write your college project report and export a submission-ready PDF in IEEE or APA. Cover pages, citations and references — formatted automatically.',
            'landing_meta_keywords' => 'report generator, final year project report, IEEE, APA, BCA, CSIT, documentation, citation, references',
            'landing_twitter_card' => 'summary_large_image',

            // Hero
            'landing_hero_eyebrow' => 'Final-year report generator',
            'landing_hero_title' => 'Document Generator',
            'landing_hero_subtitle' => 'Stop losing marks to broken margins, mismatched page numbers and inconsistent references. Build your college project report chapter by chapter, cite a source, and export a submission-ready PDF in IEEE or APA — title page, table of contents and references built automatically.',
            'landing_hero_primary_label' => 'Start your report — free',
            'landing_hero_secondary_label' => 'See how it works',
            'landing_hero_badge_1' => 'No sign-up to try',
            'landing_hero_badge_2' => 'Runs in your browser',
            'landing_hero_badge_3' => 'IEEE & APA built in',

            // Section headings
            'landing_features_eyebrow' => 'Everything formatted for you',
            'landing_features_heading' => 'The tedious parts of report writing, automated',
            'landing_features_subheading' => 'The six rules that usually eat your last weekend before submission — applied automatically, live, as you type.',
            'landing_formats_eyebrow' => 'Available formats',
            'landing_formats_heading' => "Built to your university's report format",
            'landing_formats_subheading' => "Start from a preset that matches your institution's project report guidelines — or build your own from scratch.",
            'landing_samples_eyebrow' => 'Sample reports',
            'landing_samples_heading' => 'See a finished report before you start',
            'landing_samples_subheading' => 'Browse example project documentation built with the generator, then open the editor and make your own.',

            // Footer. The copyright line supports two placeholders: :year (the
            // current year) and {company} (the company name, rendered as a link
            // to the company URL, opening in a new tab).
            'landing_footer_copyright' => '© :year {company} All rights reserved.',
            'landing_footer_tagline' => 'A document tool for final-year project reports and documentation — academic structure, referencing and pagination, in IEEE or APA.',
            'landing_footer_company_name' => config('app.name'),
            'landing_footer_company_url' => 'https://laravel.com',
        ];
    }

    /**
     * The site/brand name shown next to the logo, falling back to the app name.
     */
    public static function siteName(): string
    {
        return self::get('landing_site_name') ?: config('app.name');
    }

    /**
     * Read one landing field, falling back to its registered default.
     */
    public static function get(string $key): string
    {
        return (string) Setting::get($key, self::defaults()[$key] ?? '');
    }

    /**
     * Read a landing field's stored value as-is, with NO fallback to the default
     * once it has ever been saved. Used for optional, hideable fields (e.g. the
     * hero trust badges) where an admin clearing the box must hide the item
     * rather than silently re-show the default.
     */
    public static function getRaw(string $key): string
    {
        $map = Setting::map();

        // Key never stored → use the default; stored (even as "") → honour it.
        return (string) ($map[$key] ?? self::defaults()[$key] ?? '');
    }

    /**
     * The footer copyright line as safe HTML: :year is expanded to the current
     * year and {company} becomes the company name linking to the company URL
     * (new tab). All dynamic parts are escaped before being injected.
     */
    public static function copyrightHtml(): string
    {
        $line = e(self::get('landing_footer_copyright'));
        $line = str_replace(':year', e((string) now()->year), $line);

        $name = trim(self::get('landing_footer_company_name'));
        $url = trim(self::get('landing_footer_company_url'));

        if ($name === '') {
            // No company set — just drop the placeholder.
            return trim(str_replace('{company}', '', $line));
        }

        $company = $url !== ''
            ? '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer" class="font-medium text-gray-500 underline-offset-2 hover:text-indigo-600 hover:underline dark:text-gray-400 dark:hover:text-indigo-400">'.e($name).'</a>'
            : '<span class="font-medium">'.e($name).'</span>';

        return str_replace('{company}', $company, $line);
    }

    /**
     * Public URL of an uploaded landing image setting (e.g. the OG image), or
     * null when none is set.
     */
    public static function imageUrl(string $key): ?string
    {
        $path = Setting::get($key);

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
