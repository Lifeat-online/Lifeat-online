<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
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
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $columns = collect(Schema::getColumns('users'))->keyBy('name');

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
            $attributes['role'] = User::count() === 0 ? 'admin' : 'member';
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

        $userId = DB::table('users')->insertGetId($attributes);
        $user = User::query()->findOrFail($userId);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
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

    private function defaultForRequiredUserColumn(string $name, array $column, Request $request): mixed
    {
        $normalized = Str::lower($name);
        $type = Str::lower((string) ($column['type_name'] ?? $column['type'] ?? ''));

        if (str_contains($normalized, 'password')) {
            return Hash::make($request->password);
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
            return User::count() === 0 ? 'admin' : 'member';
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
