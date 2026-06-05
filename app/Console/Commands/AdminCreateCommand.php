<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminCreateCommand extends Command
{
    protected $signature = 'admin:create
        {--email= : Email address for the admin user (prompted if omitted)}
        {--name= : Display name (prompted if omitted)}
        {--password= : Password (generated if omitted)}
        {--role=super_admin : Primary role/slug to assign}';

    protected $description = 'Create or promote an administrator account. Intended for first-boot provisioning; run from the deployment shell only.';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?: $this->ask('Admin email'));
        $name = (string) ($this->option('name') ?: $this->ask('Display name', Str::title(Str::before($email, '@'))));
        $password = (string) ($this->option('password') ?: Str::password(20));
        $primaryRole = (string) $this->option('role');

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
            'role' => $primaryRole,
        ], [
            'email' => ['required', 'email:rfc'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
            'role' => ['required', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($email, $name, $password, $primaryRole) {
            $existingUser = User::query()->where('email', $email)->first();
            $before = $existingUser ? $this->userRoleSnapshot($existingUser) : [];

            $attributes = [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ];

            if (Schema::hasColumn('users', 'role')) {
                $attributes['role'] = $primaryRole;
            }

            $user = User::query()->updateOrCreate(['email' => $email], $attributes);

            if (Schema::hasTable('roles') && Schema::hasTable('role_user')) {
                $role = Role::query()->where('slug', $primaryRole)->first();

                if ($role) {
                    $user->roles()->syncWithoutDetaching([$role->id]);
                }
            }

            $user->refresh();
            $this->logProvisioningAudit($user, $before, $this->userRoleSnapshot($user), $primaryRole);

            return $user;
        });

        $this->info('Admin account ready.');
        $this->line("ID:       {$user->id}");
        $this->line("Email:    {$user->email}");
        $this->line("Role:     {$primaryRole}");

        if (! $this->option('password')) {
            $this->newLine();
            $this->warn('Generated password (store it now — it will not be shown again):');
            $this->line($password);
        }

        return self::SUCCESS;
    }

    private function userRoleSnapshot(User $user): array
    {
        $roleSlugs = [];

        if (Schema::hasTable('roles') && Schema::hasTable('role_user')) {
            $roleSlugs = $user->roles()
                ->pluck('slug')
                ->sort()
                ->values()
                ->all();
        }

        return [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'primary_role' => Schema::hasColumn('users', 'role') ? $user->role : null,
            'assigned_role_slugs' => $roleSlugs,
        ];
    }

    private function logProvisioningAudit(User $user, array $before, array $after, string $requestedRole): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $action = empty($before)
            ? 'admin_account.created'
            : ($this->roleChanged($before, $after, $requestedRole) ? 'admin_account.role_changed' : 'admin_account.provisioned');

        AuditLog::create([
            'actor_user_id' => null,
            'action' => $action,
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'before_json' => $before,
            'after_json' => $after + ['requested_role' => $requestedRole],
            'ip_address' => null,
            'user_agent' => 'artisan admin:create',
        ]);
    }

    private function roleChanged(array $before, array $after, string $requestedRole): bool
    {
        $beforeRole = $before['primary_role'] ?? null;
        $afterRole = $after['primary_role'] ?? null;
        $beforeSlugs = $before['assigned_role_slugs'] ?? [];
        $afterSlugs = $after['assigned_role_slugs'] ?? [];

        return $beforeRole !== $afterRole
            || ! in_array($requestedRole, $beforeSlugs, true)
            || $beforeSlugs !== $afterSlugs;
    }
}
