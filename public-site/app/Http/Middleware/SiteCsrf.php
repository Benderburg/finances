<?php

namespace App\Http\Middleware;

// The public CMS has a separate session. Never replace the finance app's
// XSRF-TOKEN cookie when the user visits the public site in another tab.
class SiteCsrf extends \Illuminate\Foundation\Http\Middleware\PreventRequestForgery
{
    protected $addHttpCookie = false;
}
