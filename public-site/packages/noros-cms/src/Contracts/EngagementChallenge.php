<?php

namespace Noros\Cms\Contracts;

use Illuminate\Http\Request;

interface EngagementChallenge
{
    /** Optional CAPTCHA integration: throw ValidationException on rejection. */
    public function validate(Request $request): void;
}
