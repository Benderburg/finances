<?php

namespace Noros\Core\Support;

use Illuminate\Support\Facades\Schema;
use Noros\Core\Models\Setting;

class SiteSeo
{
    private array $cache = [];

    public function settings(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        if (isset($this->cache[$locale])) {
            return $this->cache[$locale];
        }
        $data = $this->baseSettings($locale);
        $localized = $data['locales'][$locale] ?? [];

        return $this->cache[$locale] = array_replace($data, is_array($localized) ? $localized : []);
    }

    public function baseSettings(?string $locale = null): array
    {
        $locale ??= config('noros.fallback_locale');
        $settings = app(Settings::class);
        $hasSettings = Schema::hasTable('settings');
        $stored = $hasSettings ? Setting::where('key', 'site.seo')->first()?->value : [];
        $defaults = [
            'site_name' => $hasSettings ? $settings->get('site.name', config('app.name'), $locale) : config('app.name'),
            'default_title' => '', 'default_description' => $hasSettings ? $settings->get('site.seo_description', '', $locale) : '',
            'default_image' => $hasSettings ? $settings->get('site.og_image', '', $locale) : '',
            'robots' => $hasSettings ? $settings->get('site.robots', 'index, follow', $locale) : 'index, follow',
            'twitter_card' => 'summary_large_image', 'og_locale' => '',
            'require_translation' => false, 'x_default_locale' => config('noros.default_locale'),
            'sitemap_enabled' => true, 'robots_rules' => "User-agent: *\nDisallow: /admin\n",
            'organization_enabled' => false, 'organization_type' => 'Organization', 'organization_name' => '',
            'organization_url' => '', 'organization_logo' => '', 'organization_image' => '',
            'organization_description' => '', 'organization_phone' => '', 'organization_email' => '',
            'street_address' => '', 'address_locality' => '', 'address_region' => '', 'postal_code' => '', 'address_country' => '',
            'area_served' => [], 'available_languages' => array_keys(config('noros.locales')),
            'same_as' => [], 'opening_hours' => [], 'locales' => [],
        ];
        $data = array_replace($defaults, is_array($stored) ? $stored : []);
        if ($data['organization_type'] === 'ProfessionalService') {
            $data['organization_type'] = 'LocalBusiness';
        }

        return $data;
    }

    public function organization(?string $locale = null): array
    {
        $data = $this->settings($locale);
        if (! $data['organization_enabled']) {
            return [];
        }
        $media = app(MediaUrl::class);
        $url = $data['organization_url'] ?: url('/');
        if (! $this->isHttpUrl($url)) {
            $url = url('/');
        }
        $organization = [
            '@type' => in_array($data['organization_type'], ['Organization', 'LocalBusiness'], true) ? $data['organization_type'] : 'Organization',
            '@id' => rtrim($url, '/').'#organization', 'url' => $url,
            'name' => $data['organization_name'] ?: $data['site_name'],
        ];
        foreach (['description' => 'organization_description', 'telephone' => 'organization_phone', 'email' => 'organization_email'] as $property => $key) {
            if (filled($data[$key])) {
                $organization[$property] = $data[$key];
            }
        }
        foreach (['logo' => 'organization_logo', 'image' => 'organization_image'] as $property => $key) {
            $image = $media->resolve($data[$key]);
            if ($image && $this->isHttpUrl($image)) {
                $organization[$property] = $image;
            }
        }
        $address = array_filter([
            'streetAddress' => $data['street_address'], 'addressLocality' => $data['address_locality'],
            'addressRegion' => $data['address_region'], 'postalCode' => $data['postal_code'], 'addressCountry' => $data['address_country'],
        ], fn (mixed $value): bool => filled($value));
        if ($address !== []) {
            $organization['address'] = ['@type' => 'PostalAddress'] + $address;
        }
        if (is_array($data['area_served']) && $data['area_served'] !== []) {
            $organization['areaServed'] = array_values($data['area_served']);
        }
        if (is_array($data['same_as'])) {
            $organization['sameAs'] = array_values(array_filter($data['same_as'], fn (mixed $url): bool => $this->isHttpUrl($url)));
        }
        if (is_array($data['available_languages']) && filled($data['organization_phone'])) {
            $organization['contactPoint'] = ['@type' => 'ContactPoint', 'telephone' => $data['organization_phone'], 'contactType' => 'customer service', 'availableLanguage' => $data['available_languages']];
        }
        foreach ((array) $data['opening_hours'] as $hours) {
            if (is_array($hours) && filled($hours['days'] ?? []) && preg_match('/\A\d{2}:\d{2}\z/', $hours['opens'] ?? '') && preg_match('/\A\d{2}:\d{2}\z/', $hours['closes'] ?? '')) {
                $organization['openingHoursSpecification'][] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $hours['days'], 'opens' => $hours['opens'], 'closes' => $hours['closes']];
            }
        }

        return $organization;
    }

    public function isHttpUrl(mixed $url): bool
    {
        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
