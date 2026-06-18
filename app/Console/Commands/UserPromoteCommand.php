<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserPromoteCommand extends Command
{
    protected $signature = 'user:promote
        {--email= : Email of the user to promote (prompted if omitted)}
        {--role=dev : Target role/slug (default: dev). Use one of: dev, developer, super_admin, admin, content_manager, sales_staff, support, registered_user, business_owner, transport_driver, councillor, ai_manager, finance}
        {--list : List all available roles and exit}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Promote (or demote) an existing user to a new role. Intended for live recovery when a user has been assigned the wrong role.';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listRoles();
        }

        $email = (string) ($this->option('email') ?: $this->ask('User email'));
        $role = (string) $this->option('role');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email: {$email}");

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email: {$email}");

            return self::FAILURE;
        }

        $before = [
            'role' => $user->role,
            'role_slugs' => Schema::hasTable('roles') && Schema::hasTable('role_user')
                ? $user->roles()->pluck('slug')->sort()->values()->all()
                : [],
        ];

        $this->line("Current state for {$user->email} ({$user->name}):");
        $this->line("  Primary role : {$before['role']}");
        $this->line("  Role slugs   : ".implode(', ', $before['role_slugs'] ?: ['<none>']));
        $this->newLine();
        $this->line("Target role   : {$role}");

        if ($before['role'] === $role) {
            $this->info("User is already on role '{$role}'. Nothing to do.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run — no changes made.');

            return self::SUCCESS;
        }

        $updated = DB::transaction(function () use ($user, $role) {
            $user->role = $role;
            $user->save();

            if (Schema::hasTable('roles') && Schema::hasTable('role_user')) {
                $roleModel = Role::query()->where('slug', $role)->first();

                if ($roleModel) {
                    $user->roles()->syncWithoutDetaching([$roleModel->id]);
                }
            }

            $user->refresh();

            return $user;
        });

        $after = [
            'role' => $updated->role,
            'role_slugs' => Schema::hasTable('roles') && Schema::hasTable('role_user')
                ? $updated->roles()->pluck('slug')->sort()->values()->all()
                : [],
        ];

        if (Schema::hasTable('audit_logs')) {
            AuditLog::create([
                'actor_user_id' => null,
                'action' => 'user.role_promoted',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'before_json' => $before,
                'after_json' => $after,
                'ip_address' => null,
                'user_agent' => 'artisan user:promote',
            ]);
        }

        $this->info("User promoted. New state:");
        $this->line("  Primary role : {$after['role']}");
        $this->line("  Role slugs   : ".implode(', ', $after['role_slugs'] ?: ['<none>']));

        return self::SUCCESS;
    }

    private function listRoles(): int
    {
        if (Schema::hasTable('roles')) {
            $this->info('Roles in the database:');
            $this->table(
                ['ID', 'Slug', 'Name'],
                Role::query()->orderBy('id')->get(['id', 'slug', 'name'])
                    ->map(fn ($r) => [(int) $r->id, (string) $r->slug, (string) $r->name])
                    ->all()
            );
        } else {
            $this->warn('No roles table — only the users.role column is in use.');
        }

        $this->newLine();
        $this->line('Common role slugs: dev, developer, super_admin, admin, content_manager, sales_staff, support, registered_user, business_owner, transport_driver, councillor, ai_manager, finance');

        return self::SUCCESS;
    }
}
