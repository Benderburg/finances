<?php

namespace App\Http\Controllers;

use App\Domain\CommandBus;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Domain\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminController extends Controller
{
    private function guard(Request $r): void
    {
        if (! $r->user()->is_admin) {
            throw new DomainError('ADMIN_REQUIRED', 403);
        }
    }

    public function stats(Request $r)
    {
        $this->guard($r);
        $total = DB::table('users')->count();
        $active = DB::table('users')->where('last_seen_at', '>=', now()->subDays(30))->count();

        return ['data' => ['total' => $total, 'active_30_days' => $active, 'retention_percent' => $total ? Money::percent((string) $active, (string) $total) : '0', 'premium' => DB::table('users')->where('billing_plan', 'premium')->count(), 'admins' => DB::table('users')->where('is_admin', true)->count()]];
    }

    public function users(Request $r)
    {
        $this->guard($r);
        $r->validate(['q' => 'sometimes|string|max:255', 'page' => 'sometimes|integer|min:1']);
        $q = DB::table('users')->select('id', 'email', 'full_name', 'billing_plan', 'is_admin', 'last_seen_at', 'created_at')->when($r->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$r->query('q').'%')->orWhere('email', 'like', '%'.$r->query('q').'%')))->orderBy('created_at')->orderBy('id')->paginate(30);

        return ['data' => ['items' => $q->items(), 'pagination' => ['page' => $q->currentPage(), 'pages' => $q->lastPage(), 'total' => $q->total()]]];
    }

    public function update(Request $r, string $id, CommandBus $bus)
    {
        $this->guard($r);
        $p = Fields::check($r->all(), ['full_name' => 'sometimes|string|max:255', 'billing_plan' => 'sometimes|in:regular,premium', 'is_admin' => 'sometimes|boolean']);

        return $bus->execute($id, $r->header('Idempotency-Key', ''), 'admin:'.$r->user()->id.':'.$id, $p, function () use ($r, $p, $id) {
            if (! $r->user()->fresh()->is_admin) {
                throw new DomainError('ADMIN_REQUIRED', 403);
            } $before = DB::table('users')->where('id', $id)->first(['full_name', 'billing_plan', 'is_admin']);
            if (! $before) {
                throw new DomainError('NOT_FOUND', 404);
            } DB::table('users')->where('id', $id)->update($p + ['updated_at' => now()]);
            $after = DB::table('users')->where('id', $id)->first(['full_name', 'billing_plan', 'is_admin']);
            DB::table('admin_audit')->insert(['actor_id' => $r->user()->id, 'subject_id' => $id, 'before_payload' => json_encode($before), 'after_payload' => json_encode($after), 'created_at' => now()]);

            return (array) $after;
        });
    }
}
