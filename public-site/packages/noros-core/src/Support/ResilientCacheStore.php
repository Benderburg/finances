<?php

namespace Noros\Core\Support;

use Illuminate\Cache\FailoverStore;
use RuntimeException;
use Throwable;

/** The circuit belongs to the request/job scope, never to a long-running worker. */
class ResilientCacheStore extends FailoverStore
{
    protected function attemptOnAllStores(string $method, array $arguments)
    {
        $state = app(CacheConnectionState::class);
        $lastException = null;

        foreach ($this->stores as $store) {
            if ($state->failedStores[$store] ?? false) {
                continue;
            }
            try {
                return $this->store($store)->{$method}(...$arguments);
            } catch (Throwable $exception) {
                $state->failedStores[$store] = true;
                $lastException = $exception;
                // Do not log connection exceptions: URLs may contain credentials.
                logger()->warning('Cache store unavailable; using the next configured store.', ['store' => $store]);
            }
        }

        throw $lastException ?? new RuntimeException('No cache store is available.');
    }
}
