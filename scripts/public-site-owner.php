<?php

// Private deployment CLI. Reuse the existing owner's password hash for the
// standard Noros CMS admin; no public registration or finance access is added.
[$script, $mode, $file] = $argv + [null, null, null];
$root = '/home/c/ck85651/norocel';
if (! in_array($mode, ['export', 'import'], true) || ! is_string($file) || ! str_starts_with(realpath(dirname($file)).'/', $root.'/.deploy/')) {
    throw new RuntimeException('Expected a private deployment file within the Norocel site.');
}
if ($mode === 'export') {
    require $root.'/backend/vendor/autoload.php';
    $app = require $root.'/backend/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $owner = App\Models\User::query()->where('email', 'fritz@noros.net')->where('is_admin', true)->whereNotNull('email_verified_at')->firstOrFail();
    if (file_exists($file)) { throw new RuntimeException('Owner export already exists.'); }
    file_put_contents($file, json_encode(['email' => $owner->email, 'name' => $owner->full_name, 'password' => $owner->getRawOriginal('password')], JSON_THROW_ON_ERROR));
    chmod($file, 0600);
    echo "Existing confirmed owner exported privately\n";
} else {
    require $root.'/public-site/vendor/autoload.php';
    $app = require $root.'/public-site/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $data = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL) || ! password_get_info($data['password'])['algo']) { throw new RuntimeException('Invalid owner export.'); }
    $user = Noros\Core\Models\User::firstOrCreate(['email' => $data['email']], ['name' => $data['name'], 'password' => $data['password']]);
    if ($user->wasRecentlyCreated) { $user->forceFill(['email_verified_at' => now()])->save(); }
    $user->roles()->syncWithoutDetaching([Noros\Core\Models\Role::where('name', 'administrator')->firstOrFail()->id]);
    unlink($file);
    echo "CMS owner configured using the existing credentials; private export removed\n";
}
