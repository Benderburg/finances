<?php

namespace Noros\Cms\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VisitorIdentity
{
    public function token(Request $request): string
    {
        $token = $request->session()->get('noros.visitor') ?? $request->cookie('noros_visitor');
        if (! is_string($token) || ! Str::isUuid($token)) {
            $token = (string) Str::uuid();
        }
        $request->session()->put('noros.visitor', $token);

        return $token;
    }

    public function hash(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier();

        return hash_hmac('sha256', $userId ? 'user:'.$userId : 'visitor:'.$this->token($request), (string) config('app.key'));
    }
}
