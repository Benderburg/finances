<?php

namespace App\Policies;

use App\Models\FinancialRecord;
use App\Models\User;

final class FinancialRecordPolicy
{
    public function view(User $user, FinancialRecord $record): bool
    {
        return $record->user_id === $user->id;
    }

    public function update(User $user, FinancialRecord $record): bool
    {
        return $this->view($user, $record);
    }
}
