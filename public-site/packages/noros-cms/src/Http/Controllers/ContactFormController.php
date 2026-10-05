<?php

namespace Noros\Cms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Noros\Cms\Mail\ContactMessage;

class ContactFormController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $recipient = config('noros-cms.contact.recipient');
        abort_unless(config('noros-cms.contact.enabled', false) && is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:5', 'max:10000'],
            'company' => ['nullable', 'string', 'max:0'],
            'consent' => ['accepted'],
        ]);
        Mail::to($recipient)->send(new ContactMessage($data['name'], $data['email'], $data['message']));

        return back()->with('noros-contact-sent', __('noros-cms::blocks.sent'));
    }
}
