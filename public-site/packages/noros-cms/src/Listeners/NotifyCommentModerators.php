<?php

namespace Noros\Cms\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Noros\Cms\Events\CommentSubmitted;

class NotifyCommentModerators implements ShouldQueue
{
    public function handle(CommentSubmitted $event): void
    {
        $recipient = config('noros-cms.engagement.moderation_email');
        if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $comment = $event->comment;
        $text = 'A new comment awaits moderation. Comment #'.$comment->getKey()."\n\n".$comment->author_name."\n\n".$comment->body;
        Mail::raw($text, function (Message $message) use ($recipient): void {
            $message->to($recipient)->subject('Noros: comment awaiting moderation');
        });
    }
}
