<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'groupedSettings' => Setting::grouped(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = Setting::query()->orderBy('id')->get();
        $request->validate([
            'settings' => ['required', 'array'],
        ]);
        $input = (array) $request->input('settings', []);

        foreach ($settings as $setting) {
            if (! array_key_exists($setting->key, $input)) {
                continue;
            }

            Validator::make(
                [$setting->key => $input[$setting->key]],
                [
                    $setting->key => match ($setting->type) {
                        'integer' => ['nullable', 'integer'],
                        'decimal' => ['nullable', 'numeric'],
                        default => ['nullable', 'string'],
                    },
                ]
            )->validate();

            $before = $setting->only(['value', 'type', 'group']);
            $setting->update([
                'value' => (string) $input[$setting->key],
                'updated_by_user_id' => $request->user()?->id,
            ]);

            AuditLog::create([
                'actor_user_id' => $request->user()?->id,
                'action' => 'setting.updated',
                'subject_type' => Setting::class,
                'subject_id' => $setting->id,
                'before_json' => $before,
                'after_json' => $setting->fresh()->only(['value', 'type', 'group']),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect()->route('admin.settings.index')->with('status', 'Settings updated.');
    }
}
