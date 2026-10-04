<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

final class VerificationMail
{
    public function send(User $user): bool
    {
        try {
            $user->sendEmailVerificationNotification();

            return true;
        } catch (Throwable $e) {
            // Transport messages may include credentials or signed links.
            Log::error('Verification mail could not be submitted', [
                'user_id' => $user->id,
                'exception_class' => $e::class,
            ]);

            return false;
        }
    }
}
