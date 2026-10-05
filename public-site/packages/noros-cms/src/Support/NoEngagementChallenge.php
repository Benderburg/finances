<?php

namespace Noros\Cms\Support;

use Illuminate\Http\Request;
use Noros\Cms\Contracts\EngagementChallenge;

class NoEngagementChallenge implements EngagementChallenge
{
    public function validate(Request $request): void {}
}
