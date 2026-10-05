<?php

namespace Noros\Core\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class SafeHtml
{
    public static function clean(?string $html): string
    {
        return app(HtmlSanitizerInterface::class)->sanitize($html ?? '');
    }
}
