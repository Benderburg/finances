<?php

namespace App\Http\Controllers;

use App\Domain\DomainError;
use App\Domain\Fields;
use App\Domain\WorkspaceService;
use App\Models\User;
use App\Notifications\ConfirmEmailChange;
use App\Services\VerificationMail;
use Illuminate\Auth\Events\PasswordReset;
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
    public function register(Request $r, WorkspaceService $workspace, VerificationMail $mail)
    {
        $input = $r->all();
        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }
        $p = Fields::check($input, ['full_name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users', 'password' => ['required', 'confirmed', PasswordRule::min(12)], 'password_confirmation' => 'required|string', 'locale' => 'sometimes|in:ro,ru,en']);
        $user = DB::transaction(function () use ($p, $workspace) {
            $user = User::create(['full_name' => $p['full_name'], 'email' => mb_strtolower($p['email']), 'password' => $p['password']]);
            $workspace->initialize($user->id);
            DB::table('user_settings')->where('user_id', $user->id)->update(['locale' => $p['locale'] ?? 'ro']);

            return $user;
        });
        Auth::guard('web')->login($user);
        $r->session()->regenerate();
        $sent = $mail->send($user);

        return response()->json(['data' => $user, 'verification_sent' => $sent], 201);
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
        if (! $r->hasValidSignature()) {
            return redirect('/login?verification=invalid');
        }
        [$user, $newlyVerified] = DB::transaction(function () use ($id, $hash) {
            DB::table('user_settings')->where('user_id', $id)->lockForUpdate()->first();
            $user = User::whereKey($id)->lockForUpdate()->firstOrFail();
            if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
                abort(403);
            }
            $newlyVerified = ! $user->hasVerifiedEmail();
            if ($newlyVerified) {
                $user->markEmailAsVerified();
            }

            return [$user, $newlyVerified];
        });
        if ($newlyVerified) {
            event(new Verified($user));
            // A first confirmation proves possession of the mailbox. Do not
            // turn an already-used verification link into a reusable login.
            if (! $r->user() || $r->user()->id === $user->id) {
                Auth::guard('web')->login($user);
                $r->session()->regenerate();
            }
        }

        return redirect($r->user()?->id === $user->id ? '/?verified=1' : '/login?verified=1');
    }

    public function resend(Request $r, VerificationMail $mail)
    {
        if (! $r->user()->hasVerifiedEmail() && ! $mail->send($r->user())) {
            throw new DomainError('MAIL_DELIVERY_FAILED', 503);
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
