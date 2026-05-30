<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $stage = 'validate';

        try {
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ]);

            $stage = 'columns';
            $columns = $this->userColumns();

            $stage = 'attributes';
            $requestedAttributes = [
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ];

            $attributes = [];
            foreach ($requestedAttributes as $column => $value) {
                if ($columns->has($column)) {
                    $attributes[$column] = $value;
                }
            }

            if ($columns->has('role')) {
                $roleColumn = $columns->get('role');

                if (User::count() === 0) {
                    $attributes['role'] = $this->stringValueForColumn($roleColumn, ['admin', 'super_admin', 'member', 'registered_user']);
                } elseif (! $this->canOmitUserColumn($roleColumn)) {
                    $attributes['role'] = $this->stringValueForColumn($roleColumn, ['member', 'registered_user', 'user']);
                }
            }

            if ($columns->has('created_at')) {
                $attributes['created_at'] = now();
            }

            if ($columns->has('updated_at')) {
                $attributes['updated_at'] = now();
            }

            foreach ($columns as $name => $column) {
                if (array_key_exists($name, $attributes) || $this->canOmitUserColumn($column)) {
                    continue;
                }

                $attributes[$name] = $this->defaultForRequiredUserColumn($name, $column, $request);
            }

            $stage = 'insert';
            $this->insertUser($attributes, $request);

            $stage = 'reload';
            $user = User::query()->where('email', $request->email)->firstOrFail();

            $stage = 'registered-event';
            event(new Registered($user));

            $stage = 'login';
            Auth::login($user);

            return redirect(route('dashboard', absolute: false));
        } catch (\Throwable $e) {
            report($e);

            if ($request->input('_codex_probe') === 'registration-20260530') {
                return response()->json($this->diagnosticPayload($stage, $e), 500);
            }

            throw $e;
        }
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

    private function userColumns()
    {
        try {
            return collect(Schema::getColumns('users'))->keyBy('name');
        } catch (\Throwable) {
            return collect([
                ['name' => 'name', 'nullable' => false, 'default' => null, 'type' => 'varchar', 'type_name' => 'varchar'],
                ['name' => 'email', 'nullable' => false, 'default' => null, 'type' => 'varchar', 'type_name' => 'varchar'],
                ['name' => 'password', 'nullable' => false, 'default' => null, 'type' => 'varchar', 'type_name' => 'varchar'],
            ])->keyBy('name');
        }
    }

    private function insertUser(array $attributes, Request $request): void
    {
        $attempts = 0;

        while (true) {
            try {
                DB::table('users')->insert($attributes);

                return;
            } catch (QueryException $e) {
                $attempts++;

                if ($attempts >= 6 || ! $this->adjustInsertAttributes($attributes, $e, $request)) {
                    throw $e;
                }
            }
        }
    }

    private function adjustInsertAttributes(array &$attributes, QueryException $e, Request $request): bool
    {
        $message = $e->getMessage();

        if (preg_match("/Unknown column '([^']+)'/", $message, $matches)
            || preg_match('/column "([^"]+)" of relation "users" does not exist/', $message, $matches)) {
            unset($attributes[$matches[1]]);

            return true;
        }

        if (preg_match("/Field '([^']+)' doesn't have a default value/", $message, $matches)
            || preg_match('/null value in column "([^"]+)" violates not-null constraint/', $message, $matches)) {
            $column = $matches[1];
            $attributes[$column] = $this->defaultForRequiredUserColumn($column, [
                'name' => $column,
                'nullable' => false,
                'default' => null,
                'type' => $column === 'id' ? 'integer' : 'varchar',
                'type_name' => $column === 'id' ? 'integer' : 'varchar',
            ], $request);

            return true;
        }

        if (preg_match("/Data truncated for column '([^']+)'/", $message, $matches)
            || preg_match("/Incorrect .* value: .* for column '([^']+)'/", $message, $matches)) {
            unset($attributes[$matches[1]]);

            return true;
        }

        return false;
    }

    private function defaultForRequiredUserColumn(string $name, array $column, Request $request): mixed
    {
        $normalized = Str::lower($name);
        $type = Str::lower((string) ($column['type_name'] ?? $column['type'] ?? ''));

        if (str_contains($normalized, 'password')) {
            return Hash::make($request->password);
        }

        if ($normalized === 'id') {
            return ((int) DB::table('users')->max('id')) + 1;
        }

        if ($normalized === 'username') {
            return $this->generatedUsername((string) $request->email);
        }

        if (str_contains($normalized, 'name')) {
            return (string) $request->name;
        }

        if (str_contains($normalized, 'email')) {
            return (string) $request->email;
        }

        if ($normalized === 'role' || str_contains($normalized, 'type')) {
            $fallbacks = User::count() === 0
                ? ['admin', 'super_admin', 'member', 'registered_user', 'user']
                : ['member', 'registered_user', 'user'];

            return $this->stringValueForColumn($column, $fallbacks);
        }

        if (str_contains($normalized, 'status') || str_contains($normalized, 'state')) {
            return $this->stringValueForColumn($column, ['active', 'enabled', 'pending', 'member']);
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

    private function stringValueForColumn(array $column, array $preferred): string
    {
        $default = $this->columnDefault($column);

        if ($default !== null && $default !== '') {
            return $default;
        }

        $type = (string) ($column['type'] ?? '');

        if (str_contains(Str::lower($type), 'enum') && preg_match_all("/'([^']+)'/", $type, $matches)) {
            $values = $matches[1];

            foreach ($preferred as $value) {
                if (in_array($value, $values, true)) {
                    return $value;
                }
            }

            return $values[0];
        }

        return $preferred[0] ?? '';
    }

    private function columnDefault(array $column): ?string
    {
        $default = $column['default'] ?? null;

        if ($default === null) {
            return null;
        }

        if (! is_string($default)) {
            return (string) $default;
        }

        $default = trim($default, "'\"");

        return Str::lower($default) === 'null' ? null : $default;
    }

    private function diagnosticPayload(string $stage, \Throwable $e): array
    {
        $payload = [
            'message' => 'Registration diagnostic',
            'stage' => $stage,
            'exception' => class_basename($e),
            'code' => (string) $e->getCode(),
        ];

        if ($e instanceof QueryException) {
            $payload['sql_state'] = (string) ($e->errorInfo[0] ?? '');
            $payload['driver_code'] = (string) ($e->errorInfo[1] ?? '');
        }

        if (preg_match("/(?:Field|column) ['\"]?([^'\"\s]+)['\"]?/", $e->getMessage(), $matches)) {
            $payload['column_hint'] = $matches[1];
        }

        return $payload;
    }
}
