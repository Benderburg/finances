<x-filament-panels::page>
    <x-filament::section>
        <dl class="space-y-3">
            <div><dt>{{ __('noros-core::cache.driver') }}</dt><dd>{{ ucfirst($cacheStatus['driver'] ?? '') }}</dd></div>
            <div><dt>{{ __('noros-core::cache.status') }}</dt><dd>{{ __('noros-core::cache.'.($cacheStatus['status'] ?? 'disabled')) }}</dd></div>
            @if (($cacheStatus['status'] ?? 'disabled') !== 'disabled')
                <div><dt>{{ __('noros-core::cache.host') }}</dt><dd>{{ $cacheStatus['host'] }}</dd></div>
                <div><dt>{{ __('noros-core::cache.port') }}</dt><dd>{{ $cacheStatus['port'] }}</dd></div>
                @if ($cacheStatus['ping_ms'] !== null)
                    <div><dt>{{ __('noros-core::cache.ping') }}</dt><dd>{{ $cacheStatus['ping_ms'] }} ms</dd></div>
                @endif
            @endif
        </dl>
        <p class="mt-4">{{ __('noros-core::cache.'.(($cacheStatus['status'] ?? 'disabled') === 'error' ? 'error_description' : 'description')) }}</p>
    </x-filament::section>
</x-filament-panels::page>
