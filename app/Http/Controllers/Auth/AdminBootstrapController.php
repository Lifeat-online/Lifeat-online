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
        abort_unless(config('app.railway_admin_bootstrap_enabled'), 404);

        $email = (string) config('app.railway_admin_bootstrap_email');
        $password = (string) config('app.railway_admin_bootstrap_password');
        $name = (string) config('app.railway_admin_bootstrap_name');

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
