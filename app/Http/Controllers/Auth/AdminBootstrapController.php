<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminBootstrapController extends Controller
{
    public function store(Request $request)
    {
        $enabled = filter_var(env('RAILWAY_ADMIN_BOOTSTRAP_ENABLED', false), FILTER_VALIDATE_BOOL);
        abort_unless($enabled, 404);

        $email = (string) env('RAILWAY_ADMIN_EMAIL');
        $password = (string) env('RAILWAY_ADMIN_PASSWORD');
        $name = (string) (env('RAILWAY_ADMIN_NAME') ?: 'Admin');

        if (! $email || ! $password) {
            return response()->json([
                'ok' => false,
                'message' => 'Admin bootstrap is enabled, but credentials are missing.',
            ], 422);
        }

        try {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                    'role' => 'super_admin',
                    'email_verified_at' => now(),
                ],
            );

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
}
