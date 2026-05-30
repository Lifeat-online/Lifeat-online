<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AdminBootstrapController extends Controller
{
    private const ENABLED = true;

    public function store(Request $request)
    {
        abort_unless(self::ENABLED, 404);
        abort_unless(app()->environment('local') || $this->isRailway($request), 404);

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
            $columns = collect(Schema::getColumnListing('users'))->flip();

            $attributes = [
                'name' => $name,
                'password' => Hash::make($password),
            ];

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
                $attributes['email'] = $email;

                if ($columns->has('created_at')) {
                    $attributes['created_at'] = now();
                }

                if ($columns->has('updated_at')) {
                    $attributes['updated_at'] = now();
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

    private function isRailway(Request $request): bool
    {
        $host = (string) $request->getHost();
        $forwardedHost = (string) $request->header('x-forwarded-host', '');

        $hostLooksRailway = str_contains($host, 'railway.app') || str_contains($forwardedHost, 'railway.app');

        return $hostLooksRailway
            || (bool) getenv('RAILWAY_ENVIRONMENT')
            || (bool) getenv('RAILWAY_PROJECT_ID')
            || (bool) getenv('RAILWAY_SERVICE_ID')
            || (bool) getenv('RAILWAY_PUBLIC_DOMAIN')
            || (bool) getenv('RAILWAY_DEPLOYMENT_ID');
    }
}
