<?php

namespace Noros\Core\Events;

final readonly class PlatformConfigurationChanged
{
    public function __construct(public array $configuration) {}
}
