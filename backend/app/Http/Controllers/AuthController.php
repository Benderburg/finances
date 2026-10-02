<?php

namespace App\Http\Controllers;

use App\Domain\DomainError;
use App\Domain\Fields;
use App\Domain\WorkspaceService;
use App\Models\User;
use App\Notifications\ConfirmEmailChange;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

final class AuthController extends Controller
{
    public function register(Request $r, WorkspaceService $workspace)
    {
        $p = Fields::check($r->all(), ['full_name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users', 'password' => ['required', 'confirmed', PasswordRule::min(12)], 'password_confirmation' => 'required|string']);
        $user = DB::transaction(function () use ($p, $workspace) {
            $user = User::create(['full_name' => $p['full_name'], 'email' => mb_strtolower($p['email']), 'password' => $p['password']]);
            $workspace->initialize($user->id);

            return $user;
        });
        event(new Registered($user));
        Auth::guard('web')->login($user);
        $r->session()->regenerate();

        return response()->json(['data' => $user], 201);
    }

    public function login(Request $r)
    {
        $p = Fields::check($r->all(), ['email' => 'required|email', 'password' => 'required|string']);
        $p['email'] = mb_strtolower($p['email']);
        if (! Auth::guard('web')->attempt($p)) {
            throw new DomainError('INVALID_CREDENTIALS', 422);
        }
        $r->session()->regenerate();
        $user = Auth::guard('web')->user();
        $user->forceFill(['last_seen_at' => now()])->save();

        return ['data' => $user];
    }

    public function logout(Request $r)
    {
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return ['data' => ['logged_out' => true]];
    }

    public function forgot(Request $r)
    {
        $p = Fields::check($r->all(), ['email' => 'required|email']);
        Password::sendResetLink(['email' => mb_strtolower($p['email'])]);

        return ['data' => ['message' => 'RESET_LINK_SENT']];
    }

    public function reset(Request $r)
    {
        $p = Fields::check($r->all(), ['email' => 'required|email', 'token' => 'required|string', 'password' => ['required', 'confirmed', PasswordRule::min(12)], 'password_confirmation' => 'required|string']);
        $status = Password::reset($p, function (User $user, string $password) {
            DB::transaction(function () use ($user, $password) {
                DB::table('user_settings')->where('user_id', $user->id)->lockForUpdate()->first();
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            });
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw new DomainError('INVALID_RESET_TOKEN');
        }

        return ['data' => ['message' => 'PASSWORD_RESET']];
    }

    public function verify(Request $r, string $id, string $hash)
    {
        $user = User::findOrFail($id);
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }
        DB::transaction(function () use ($user) {
            DB::table('user_settings')->where('user_id', $user->id)->lockForUpdate()->first();
            $user->markEmailAsVerified();
        });
        event(new Verified($user));

        return redirect('/?verified=1');
    }

    public function resend(Request $r)
    {
        if (! $r->user()->hasVerifiedEmail()) {
            $r->user()->sendEmailVerificationNotification();
        }

        return ['data' => ['message' => 'VERIFICATION_SENT']];
    }

    public function changePassword(Request $r)
    {
        $p = Fields::check($r->all(), ['current_password' => 'required|string', 'password' => ['required', 'confirmed', PasswordRule::min(12)], 'password_confirmation' => 'required|string']);
        DB::transaction(function () use ($r, $p) {
            DB::table('user_settings')->where('user_id', $r->user()->id)->lockForUpdate()->first();
            $u = $r->user()->fresh();
            if (! Hash::check($p['current_password'], $u->password)) {
                throw new DomainError('INVALID_CREDENTIALS');
            } $u->forceFill(['password' => $p['password'], 'remember_token' => Str::random(60)])->save();
        });
        $r->session()->regenerate();
        $r->session()->put('password_hash_web', $r->user()->fresh()->password);

        return ['data' => ['message' => 'PASSWORD_CHANGED']];
    }

    public function emailChange(Request $r)
    {
        $p = Fields::check($r->all(), ['email' => 'required|email|max:255|unique:users,email', 'current_password' => 'required|string']);
        $email = mb_strtolower($p['email']);
        $id = $r->user()->id;
        DB::transaction(function () use ($id, $email, $p) {
            DB::table('user_settings')->where('user_id', $id)->lockForUpdate()->first();
            $u = User::findOrFail($id);
            if (! Hash::check($p['current_password'], $u->password)) {
                throw new DomainError('INVALID_CREDENTIALS');
            } $u->forceFill(['pending_email' => $email])->save();
        });
        $url = URL::temporarySignedRoute('email.confirm', now()->addMinutes(60), ['id' => $id, 'hash' => sha1($email)]);
        Notification::route('mail', $email)->notify(new ConfirmEmailChange($url));

        return ['data' => ['message' => 'EMAIL_CONFIRMATION_SENT', 'pending_email' => $email]];
    }

    public function confirmEmail(Request $r, string $id, string $hash)
    {
        DB::transaction(function () use ($id, $hash) {
            DB::table('user_settings')->where('user_id', $id)->lockForUpdate()->first();
            $u = User::findOrFail($id);
            if (! $u->pending_email || ! hash_equals(sha1($u->pending_email), $hash)) {
                abort(403);
            } $u->forceFill(['email' => $u->pending_email, 'pending_email' => null, 'email_verified_at' => now()])->save();
        });

        return redirect('/settings?email_confirmed=1');
    }
}
