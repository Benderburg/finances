<?php

namespace Noros\Cms\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Noros\Core\Support\Settings;

class EngagementSettings
{
    public function current(): array
    {
        $module = ['comments_enabled' => null, 'ratings_enabled' => null, 'moderation' => true, 'guest_comments' => true, 'guest_ratings' => true, 'require_login' => false, 'allow_change_vote' => true, 'rating_scale' => 'stars_5'];

        return array_replace_recursive([
            'global' => ['comments_enabled' => true, 'ratings_enabled' => true],
            'blog' => $module,
            'shop' => $module + ['verified_purchase' => false, 'related_enabled' => true, 'related_limit' => 4],
        ], (array) app(Settings::class)->get('engagement', [], config('noros.default_locale')));
    }

    public function module(string $type): array
    {
        $settings = $this->current();

        return $settings[$type === 'product' ? 'shop' : 'blog'];
    }

    public function enabled(Model $entity, string $feature): bool
    {
        if (! config('noros-cms.features.'.$feature, true)) {
            return false;
        }
        $key = $feature.'_enabled';
        $override = $entity->getAttribute('engagement_overrides')[$key] ?? null;
        if ($override !== null) {
            return (bool) $override;
        }
        // Preserve the existing per-entity opt-out. Legacy true means enabled by default.
        if ($entity->getAttribute('allow_'.$feature) !== null && ! $entity->getAttribute('allow_'.$feature)) {
            return false;
        }
        $settings = $this->current();
        $module = $settings[$entity->getMorphClass() === 'product' ? 'shop' : 'blog'];

        return (bool) ($module[$key] ?? $settings['global'][$key]);
    }

    public function permitsGuest(string $type, string $feature): bool
    {
        $module = $this->module($type);

        return ! $module['require_login'] && (bool) $module['guest_'.$feature];
    }

    public function scale(string $type): array
    {
        return config('noros-cms.engagement.scales.'.$this->module($type)['rating_scale'], ['min' => 1, 'max' => 5]);
    }

    public function validate(array $data): array
    {
        $rules = ['global' => ['required', 'array:comments_enabled,ratings_enabled']];
        foreach (['comments_enabled', 'ratings_enabled'] as $field) {
            $rules['global.'.$field] = ['required', 'boolean'];
        }
        foreach (['blog', 'shop'] as $module) {
            $rules[$module] = ['required', 'array'];
            foreach (['comments_enabled', 'ratings_enabled'] as $field) {
                $rules[$module.'.'.$field] = ['present', 'nullable', 'boolean'];
            }
            foreach (['moderation', 'guest_comments', 'guest_ratings', 'require_login', 'allow_change_vote'] as $field) {
                $rules[$module.'.'.$field] = ['required', 'boolean'];
            }
            $rules[$module.'.rating_scale'] = ['required', Rule::in(array_keys(config('noros-cms.engagement.scales', ['stars_5' => []])))];
        }
        $rules['shop.verified_purchase'] = ['required', 'boolean'];
        $rules['shop.related_enabled'] = ['required', 'boolean'];
        $rules['shop.related_limit'] = ['required', 'integer', 'between:1,24'];

        return Validator::make($data, $rules)->validate();
    }
}
