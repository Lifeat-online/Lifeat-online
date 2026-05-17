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
        ]);

        $result = $webPush->sendManual($request->user(), [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'url' => $validated['url'] ?? route('admin.dashboard'),
        ], $validated['audience']);

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
                'url' => $validated['url'] ?? route('admin.dashboard'),
                'audience' => $validated['audience'],
                'sender_user_id' => $request->user()->id,
                'web_push' => $result,
            ],
        );

        return back()->with('status', "Push attempted for {$result['attempted']} subscription(s): {$result['sent']} sent, {$result['failed']} failed.");
    }
}
