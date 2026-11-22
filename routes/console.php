<?php

use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Security\GeoIp;
use App\Support\Security\IpBlocker;
use App\Support\Stats\VisitorStats;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Needs the server cron: * * * * * php8.4 /usr/share/nginx/html/devops/toneeldev/artisan schedule:run

Artisan::command('geoip:update', function (GeoIp $geo): void {
    $this->info('Gedownload: '.$geo->update());
})->purpose('Download the free DB-IP country database used for login and visitor countries');

Schedule::command('geoip:update')->monthlyOn(3, '04:20');

Artisan::command('security:unblock {ip?} {--all : Remove every block}', function (): int {
    $query = BlockedIp::query();

    if (! $this->option('all')) {
        if (! $this->argument('ip')) {
            $this->error('Geef een IP-adres of gebruik --all.');

            return 1;
        }

        $query->where('ip', $this->argument('ip'));
    }

    $count = 0;
    $query->get()->each(function (BlockedIp $block) use (&$count): void {
        $block->delete();
        $count++;
    });

    IpBlocker::flush();
    $this->info("{$count} blokkering(en) verwijderd.");

    return 0;
})->purpose('Remove an IP block (when you locked yourself out of the admin site)');

Artisan::command('logs:prune', function (VisitorStats $stats): void {
    $logins = LoginAttempt::query()->where('created_at', '<', now()->subDays(max(30, (int) settings()->get('security.retention_days', 365))))->delete();
    $expired = BlockedIp::query()->where('is_automatic', true)->whereNotNull('blocked_until')->where('blocked_until', '<', now()->subDays(30))->delete();
    $views = $stats->prune((int) settings()->get('stats.retention_days', 730));
    IpBlocker::flush();
    $this->info("{$logins} aanmeldpogingen, {$expired} verlopen blokkeringen en {$views} paginaweergaven opgeruimd.");
})->purpose('Remove old login attempts, expired automatic blocks and old page views');

Schedule::command('logs:prune')->dailyAt('03:40');

Artisan::command('toneel:admin {email} {--name= : Name shown in the admin site} {--password= : Skip the prompt}', function (): int {
    $email = mb_strtolower(trim((string) $this->argument('email')));

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Geen geldig e-mailadres.');

        return 1;
    }

    $password = (string) ($this->option('password') ?? $this->secret('Wachtwoord (minstens 8 tekens)'));

    if (mb_strlen($password) < 8 || ($this->option('password') === null && $password !== (string) $this->secret('Herhaal het wachtwoord'))) {
        $this->error('Wachtwoord te kort of niet gelijk.');

        return 1;
    }

    $definition = Permissions::defaultRoles()['Beheerder'];
    $admin = Role::query()->where('is_admin', true)->first()
        ?? Role::query()->create(['name' => 'Beheerder', 'description' => $definition['description'], 'is_admin' => true, 'permissions' => []]);
    $user = User::query()->firstOrNew(['email' => $email]);
    $user->fill(['name' => $this->option('name') ?: ($user->name ?: $email), 'password' => $password, 'is_active' => true])->save();
    $user->roles()->syncWithoutDetaching([$admin->id]);

    $this->info(($user->wasRecentlyCreated ? 'Beheerder aangemaakt: ' : 'Bijgewerkt en beheerder gemaakt: ').$email.' - aanmelden via '.url('/admin'));

    return 0;
})->purpose('Create (or reset) an administrator for the admin site');

Artisan::command('shop:expire-orders', function (\App\Support\Shop\OrderService $orders): void {
    $count = $orders->expireStale();

    if ($count > 0) {
        $this->info("{$count} onbetaalde online bestelling(en) geannuleerd, stock teruggezet.");
    }
})->purpose('Cancel online bar orders that were never paid and put their stock back');

Schedule::command('shop:expire-orders')->everyFiveMinutes()->withoutOverlapping();
