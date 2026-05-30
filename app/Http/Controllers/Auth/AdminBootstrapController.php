<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminBootstrapController extends Controller
{
    private const ENABLED = true;

    public function store(Request $request)
    {
        abort_unless(self::ENABLED, 404);
        abort_unless(app()->environment('local') || $this->hasBootstrapToken($request), 404);

        $email = 'jameskoen78@gmail.com';
        $password = 'James4James@1978';
        $name = 'James Koen';

        if (! $email || ! $password) {
            return response()->json([
                'ok' => false,
                'message' => 'Admin bootstrap is enabled, but credentials are missing.',
            ], 422);
        }

        try {
            $columns = collect(Schema::getColumns('users'))->keyBy('name');

            $attributes = [];

            if ($columns->has('name')) {
                $attributes['name'] = $name;
            }

            if ($columns->has('password')) {
                $attributes['password'] = Hash::make($password);
            }

            if ($columns->has('email_verified_at')) {
                $attributes['email_verified_at'] = now();
            }

            if ($columns->has('role')) {
                $attributes['role'] = 'super_admin';
            }

            $userId = DB::table('users')->where('email', $email)->value('id');

            if ($userId) {
                if ($columns->has('updated_at')) {
                    $attributes['updated_at'] = now();
                }

                DB::table('users')->where('id', $userId)->update($attributes);
            } else {
                if ($columns->has('email')) {
                    $attributes['email'] = $email;
                }

                if ($columns->has('created_at')) {
                    $attributes['created_at'] = now();
                }

                if ($columns->has('updated_at')) {
                    $attributes['updated_at'] = now();
                }

                foreach ($columns as $column => $definition) {
                    if (array_key_exists($column, $attributes) || $this->canOmitUserColumn($definition)) {
                        continue;
                    }

                    $attributes[$column] = $this->defaultForRequiredUserColumn($column, $definition, $name, $email, $password);
                }

                $userId = DB::table('users')->insertGetId($attributes);
            }

            $user = User::query()->findOrFail($userId);

            Auth::logout();
            Auth::login($user, true);
            $request->session()->regenerate();

            return response()->json([
                'ok' => true,
                'redirect' => route('admin.dashboard'),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Failed to bootstrap admin account. Check logs.',
            ], 500);
        }
    }

    private function hasBootstrapToken(Request $request): bool
    {
        $configuredToken = (string) config('app.admin_bootstrap_token', '');

        if ($configuredToken === '') {
            return false;
        }

        $providedToken = (string) ($request->bearerToken()
            ?: $request->header('X-Admin-Bootstrap-Token', '')
            ?: $request->input('token', ''));

        return $providedToken !== '' && hash_equals($configuredToken, $providedToken);
    }

    private function canOmitUserColumn(array $column): bool
    {
        if (($column['auto_increment'] ?? false) || filled($column['generation'] ?? null)) {
            return true;
        }

        if (($column['nullable'] ?? true) === true) {
            return true;
        }

        if (array_key_exists('default', $column) && $column['default'] !== null) {
            return true;
        }

        return in_array($column['name'] ?? null, ['id'], true);
    }

    private function defaultForRequiredUserColumn(string $column, array $definition, string $name, string $email, string $password): mixed
    {
        $normalized = Str::lower($column);
        $type = Str::lower((string) ($definition['type_name'] ?? $definition['type'] ?? ''));

        if (str_contains($normalized, 'password')) {
            return Hash::make($password);
        }

        if ($normalized === 'username') {
            return $this->generatedUsername($email);
        }

        if (str_contains($normalized, 'name')) {
            return $name;
        }

        if (str_contains($normalized, 'email')) {
            return $email;
        }

        if ($normalized === 'role' || str_contains($normalized, 'type')) {
            return 'super_admin';
        }

        if (str_contains($normalized, 'status') || str_contains($normalized, 'state')) {
            return 'active';
        }

        if (str_contains($normalized, 'locale')) {
            return app()->getLocale() ?: 'en';
        }

        if (str_contains($type, 'bool')) {
            return false;
        }

        if (str_contains($type, 'int') || str_contains($type, 'decimal') || str_contains($type, 'double') || str_contains($type, 'float')) {
            return 0;
        }

        if (str_contains($type, 'date') || str_contains($type, 'time')) {
            return now();
        }

        if (str_contains($type, 'json')) {
            return json_encode([]);
        }

        return '';
    }

    private function generatedUsername(string $email): string
    {
        $localPart = Str::before($email, '@') ?: 'user';
        $username = preg_replace('/[^A-Za-z0-9_]+/', '_', Str::lower($localPart)) ?: 'user';

        return Str::limit(trim($username, '_').'_'.Str::lower(Str::random(6)), 255, '');
    }
}
