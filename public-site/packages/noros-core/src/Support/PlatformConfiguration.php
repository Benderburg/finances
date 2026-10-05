<?php

namespace Noros\Core\Support;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformConfiguration
{
    public function validate(array $value): array
    {
        $supported = array_keys(config('noros.supported_locales', config('noros.locales')));
        $validated = Validator::make($value, [
            'enabled_locales' => ['required', 'array', 'min:1'],
            'enabled_locales.*' => ['required', 'string', 'distinct', Rule::in($supported)],
            'default_locale' => ['required', 'string', Rule::in($supported)],
            'fallback_locale' => ['required', 'string', Rule::in($supported)],
            'timezone' => ['required', 'timezone'],
        ])->validate();
        if (! in_array($validated['default_locale'], $validated['enabled_locales'], true) || ! in_array($validated['fallback_locale'], $validated['enabled_locales'], true)) {
            throw ValidationException::withMessages(['enabled_locales' => 'Default and fallback locales must remain enabled.']);
        }

        return $validated;
    }

    public function apply(array $value): void
    {
        $value = $this->validate($value);
        config(['noros.locales' => array_intersect_key(config('noros.supported_locales'), array_flip($value['enabled_locales'])), 'noros.default_locale' => $value['default_locale'], 'noros.fallback_locale' => $value['fallback_locale'], 'app.timezone' => $value['timezone']]);
        app()->setLocale($value['default_locale']);
        app()->setFallbackLocale($value['fallback_locale']);
        date_default_timezone_set($value['timezone']);
        URL::defaults(['locale' => $value['default_locale']]);
    }
}
