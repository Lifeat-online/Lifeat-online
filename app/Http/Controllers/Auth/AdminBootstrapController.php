<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminBootstrapController extends Controller
{
    private const ENABLED = true;

    public function store(Request $request)
    {
        abort_unless(self::ENABLED, 404);

        $host = (string) $request->getHost();
        $isRailwayHost = str_ends_with($host, 'railway.app') || str_contains($host, '.railway.app');
        abort_unless(app()->environment('local') || $isRailwayHost, 404);

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
