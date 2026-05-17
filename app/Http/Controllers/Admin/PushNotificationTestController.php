<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrowserPushSubscription;
use App\Services\NotificationLogService;
use App\Services\WebPushDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PushNotificationTestController extends Controller
{
    public function index(Request $request, WebPushDeliveryService $webPush): View
    {
        return view('admin.push-notifications.test', [
            'isConfigured' => $webPush->isConfigured(),
            'activeSubscriptionCount' => BrowserPushSubscription::active()->count(),
            'ownSubscriptionCount' => BrowserPushSubscription::active()
                ->where('user_id', $request->user()->id)
                ->count(),
        ]);
    }

    public function store(Request $request, WebPushDeliveryService $webPush, NotificationLogService $notifications): RedirectResponse
    {
        $validated = $request->validate([
            'audience' => ['required', Rule::in(['all', 'self'])],
            'title' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:180'],
            'url' => ['nullable', 'url', 'max:2048'],
            'icon' => ['nullable', 'url', 'max:2048'],
            'badge' => ['nullable', 'url', 'max:2048'],
            'image' => ['nullable', 'url', 'max:2048'],
            'tag' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9:_-]+$/'],
            'topic' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            'urgency' => ['nullable', Rule::in(['very-low', 'low', 'normal', 'high'])],
            'ttl' => ['nullable', 'integer', 'min:0', 'max:2419200'],
            'require_interaction' => ['nullable', 'boolean'],
            'renotify' => ['nullable', 'boolean'],
            'silent' => ['nullable', 'boolean'],
            'play_tone' => ['nullable', 'boolean'],
            'tone' => ['nullable', Rule::in(['chime', 'bell', 'urgent', 'soft'])],
            'vibration' => ['nullable', Rule::in(['none', 'short', 'double', 'urgent'])],
            'action_1_title' => ['nullable', 'string', 'max:24'],
            'action_1_url' => ['nullable', 'url', 'max:2048'],
            'action_2_title' => ['nullable', 'string', 'max:24'],
            'action_2_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $actions = collect([1, 2])
            ->map(function (int $index) use ($validated): ?array {
                $title = $validated["action_{$index}_title"] ?? null;
                $url = $validated["action_{$index}_url"] ?? null;

                if (! $title || ! $url) {
                    return null;
                }

                return [
                    'action' => 'action-'.$index,
                    'title' => $title,
                    'url' => $url,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $tag = $validated['tag'] ?? 'admin-manual-push-'.$request->user()->id;
        $url = $validated['url'] ?? route('admin.dashboard');

        $payload = [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'url' => $url,
            'icon' => ($validated['icon'] ?? null) ?: asset('pwa/icon-192.png'),
            'badge' => ($validated['badge'] ?? null) ?: asset('pwa/favicon-32x32.png'),
            'image' => $validated['image'] ?? null,
            'tag' => $tag,
            'requireInteraction' => $request->boolean('require_interaction'),
            'renotify' => $request->boolean('renotify'),
            'silent' => $request->boolean('silent'),
            'playTone' => $request->boolean('play_tone'),
            'tone' => $validated['tone'] ?? 'chime',
            'vibration' => $validated['vibration'] ?? 'double',
            'actions' => $actions,
        ];

        $options = [
            'TTL' => (int) ($validated['ttl'] ?? 86400),
            'urgency' => $validated['urgency'] ?? 'normal',
            'topic' => ($validated['topic'] ?? null) ?: null,
        ];

        $result = $webPush->sendManual($request->user(), [
            ...$payload,
        ], $validated['audience'], $options);

        if (! $result['configured']) {
            return back()
                ->withInput()
                ->withErrors(['push' => 'Web push is not configured. Add the VAPID public and private keys before sending notifications.']);
        }

        if ($result['attempted'] === 0) {
            return back()
                ->withInput()
                ->withErrors(['push' => $validated['audience'] === 'self'
                    ? 'No active browser subscription was found for your account. Enable alerts in this browser first.'
                    : 'No active browser subscriptions were found. Ask users to enable alerts first.']);
        }

        $notifications->log(
            'admin_manual_push_sent',
            null,
            $validated['audience'] === 'self' ? $request->user()->email : 'all active browser subscriptions',
            'push',
            $result['failed'] > 0 ? 'attention' : 'sent',
            [
                'title' => $validated['title'],
                'body' => $validated['body'],
                'url' => $url,
                'audience' => $validated['audience'],
                'payload' => $payload,
                'protocol' => $options,
                'sender_user_id' => $request->user()->id,
                'web_push' => $result,
            ],
        );

        return back()->with('status', "Push attempted for {$result['attempted']} subscription(s): {$result['sent']} sent, {$result['failed']} failed.");
    }
}
