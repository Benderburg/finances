<?php

namespace Noros\Cms\Mail;

use Illuminate\Mail\Mailable;

class ContactMessage extends Mailable
{
    public function __construct(public string $senderName, public string $senderEmail, public string $messageText) {}

    public function build(): static
    {
        return $this->subject(__('noros-cms::blocks.contact_subject'))
            ->replyTo($this->senderEmail)
            ->text('noros-cms::mail.contact');
    }
}
