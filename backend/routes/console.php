<?php

use App\Console\Commands\LaunchCheck;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AccountService;
use App\Services\AnnouncementService;
use App\Services\ReminderService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/*
| Scheduled tasks. Run the scheduler with `php artisan schedule:work` locally, or a cron entry
| `* * * * * php /path/to/artisan schedule:run` in production.
*/

Artisan::command('rivalo:process-account-deletions', function (AccountService $accounts) {
    $count = $accounts->processDueDeletions();
    $this->info("Anonymized {$count} account(s) whose deletion grace period ended.");
})->purpose('Anonymize accounts whose deletion grace period has ended');

Schedule::command('rivalo:process-account-deletions')->hourly()->withoutOverlapping();

Artisan::command('rivalo:send-reminders', function (ReminderService $reminders) {
    $done = $reminders->run();
    $this->info("Match reminders checked: {$done['matches']}. Check-in reminders: {$done['check_ins']}.");
})->purpose('Send match and check-in reminders (each player gets each reminder once)');

Artisan::command('rivalo:dispatch-announcements', function (AnnouncementService $announcements) {
    $this->info('Announcements sent: '.$announcements->dispatchDue());
})->purpose('Send scheduled announcements whose time has come');

Schedule::command('rivalo:send-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('rivalo:dispatch-announcements')->everyMinute()->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();

// Lets `rivalo:launch-check` and the admin dashboard confirm the scheduler (cron) is running.
Schedule::call(fn () => Cache::forever(LaunchCheck::HEARTBEAT_KEY, now()->toIso8601String()))->everyMinute()->name('rivalo-heartbeat');

Artisan::command('rivalo:prune-idle-sessions', function () {
    $days = (int) config('rivalo.security.idle_session_days');
    $cutoff = now()->subDays($days);
    // Deleting the token also removes that device's push token (foreign key cascade).
    $count = DB::table('personal_access_tokens')
        ->where(fn ($q) => $q->where('last_used_at', '<', $cutoff)->orWhere(fn ($q) => $q->whereNull('last_used_at')->where('created_at', '<', $cutoff)))
        ->delete();
    $this->info("Signed out {$count} app session(s) unused for {$days} days.");
})->purpose('Sign out app sessions that have not been used for a long time');

Schedule::command('rivalo:prune-idle-sessions')->daily();

/*
| Create or promote the first super admin interactively. Credentials are typed by the operator,
| never stored in code or seeders.
*/
Artisan::command('rivalo:make-admin {email} {username}', function (string $email, string $username) {
    $user = User::where('email', mb_strtolower($email))->first();

    if (! $user) {
        $password = $this->secret('Password for the new super admin (min 12 characters)');
        $validator = Validator::make(
            ['password' => $password, 'username' => $username],
            ['password' => ['required', Password::min(12)->letters()->numbers()->symbols()], 'username' => ['required', 'regex:/^[a-z0-9_.]{3,30}$/']],
        );
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return 1;
        }

        $user = User::create([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'display_name' => $username,
        ]);
        $user->forceFill(['email_verified_at' => now(), 'status' => UserStatus::Active])->save();
    }

    $user->syncRoles([RoleName::SuperAdmin->value]);
    $this->info("{$user->email} is now a super admin. They must enroll an authenticator app at first sign-in to /admin.");

    return 0;
})->purpose('Create or promote a super admin');
