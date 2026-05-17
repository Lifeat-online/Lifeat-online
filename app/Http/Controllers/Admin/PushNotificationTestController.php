<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\BrowserPushSubscription;
use App\Models\Classified;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Voucher;
use App\Services\NotificationLogService;
use App\Services\WebPushDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'attachmentGroups' => $this->attachmentGroups(),
            'attachmentPresets' => $this->attachmentPresets(),
        ]);
    }

    public function store(Request $request, WebPushDeliveryService $webPush, NotificationLogService $notifications): RedirectResponse
    {
        $validated = $request->validate([
            'audience' => ['required', Rule::in(['all', 'self'])],
            'title' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:180'],
            'attachment_type' => ['nullable', Rule::in(['listing', 'event', 'voucher', 'article', 'classified'])],
            'attachment_id' => ['nullable', 'integer'],
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

        $attachment = $this->resolveAttachment(
            $validated['attachment_type'] ?? null,
            isset($validated['attachment_id']) ? (int) $validated['attachment_id'] : null,
        );

        if (($validated['attachment_type'] ?? null) && ! $attachment) {
            throw ValidationException::withMessages([
                'attachment_id' => 'Choose a valid item to attach to this notification.',
            ]);
        }

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
        $submittedUrl = $validated['url'] ?? null;
        $defaultUrl = route('admin.dashboard');
        $url = $attachment && (! $submittedUrl || $submittedUrl === $defaultUrl)
            ? $attachment['url']
            : ($submittedUrl ?: $defaultUrl);
        $image = ($validated['image'] ?? null) ?: ($attachment['image'] ?? null);

        $payload = [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'url' => $url,
            'icon' => ($validated['icon'] ?? null) ?: asset('pwa/icon-192.png'),
            'badge' => ($validated['badge'] ?? null) ?: asset('pwa/favicon-32x32.png'),
            'image' => $image,
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
            'topic' => ! empty($validated['topic']) ? $validated['topic'] : null,
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
                'attachment' => $attachment,
                'payload' => $payload,
                'protocol' => $options,
                'sender_user_id' => $request->user()->id,
                'web_push' => $result,
            ],
        );

        return back()->with('status', "Push attempted for {$result['attempted']} subscription(s): {$result['sent']} sent, {$result['failed']} failed.");
    }

    private function attachmentGroups(): array
    {
        return [
            'listing' => [
                'label' => 'Businesses / listings',
                'items' => Listing::query()
                    ->orderBy('title')
                    ->limit(150)
                    ->get(['id', 'title', 'slug', 'city', 'region', 'featured_image', 'logo_path'])
                    ->map(fn (Listing $listing): array => [
                        'type' => 'listing',
                        'id' => $listing->id,
                        'label' => $listing->title,
                        'meta' => collect([$listing->city, $listing->region])->filter()->join(', '),
                        'url' => route('directory.show', $listing),
                        'image' => $this->mediaUrl($listing->featured_image ?: $listing->logo_path),
                        'primaryAction' => 'View business',
                        'secondaryAction' => 'Get directions',
                    ])
                    ->values()
                    ->all(),
            ],
            'event' => [
                'label' => 'Events',
                'items' => Event::query()
                    ->with('listing:id,title')
                    ->orderByDesc('start_at')
                    ->limit(150)
                    ->get(['id', 'listing_id', 'title', 'slug', 'city', 'start_at', 'featured_image'])
                    ->map(fn (Event $event): array => [
                        'type' => 'event',
                        'id' => $event->id,
                        'label' => $event->title,
                        'meta' => collect([
                            optional($event->start_at)->format('M j, Y'),
                            $event->listing?->title,
                            $event->city,
                        ])->filter()->join(' - '),
                        'url' => route('events.show', $event),
                        'image' => $this->mediaUrl($event->featured_image),
                        'primaryAction' => 'View event',
                        'secondaryAction' => 'Browse events',
                    ])
                    ->values()
                    ->all(),
            ],
            'voucher' => [
                'label' => 'Vouchers',
                'items' => Voucher::query()
                    ->with('listing:id,title,slug,featured_image,logo_path')
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->limit(150)
                    ->get(['id', 'listing_id', 'title', 'slug', 'voucher_type', 'discount_amount', 'discount_percent', 'currency', 'status', 'published_at'])
                    ->filter(fn (Voucher $voucher): bool => $voucher->listing !== null)
                    ->map(fn (Voucher $voucher): array => [
                        'type' => 'voucher',
                        'id' => $voucher->id,
                        'label' => $voucher->title,
                        'meta' => collect([$voucher->formattedValue(), $voucher->listing?->title, ucfirst((string) $voucher->status)])->filter()->join(' - '),
                        'url' => route('vouchers.show', ['listing' => $voucher->listing, 'voucher' => $voucher]),
                        'image' => $this->mediaUrl($voucher->listing?->featured_image ?: $voucher->listing?->logo_path),
                        'primaryAction' => 'Claim voucher',
                        'secondaryAction' => 'View business',
                    ])
                    ->values()
                    ->all(),
            ],
            'article' => [
                'label' => 'Articles',
                'items' => Article::query()
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->limit(150)
                    ->get(['id', 'title', 'slug', 'status', 'published_at', 'featured_image'])
                    ->map(fn (Article $article): array => [
                        'type' => 'article',
                        'id' => $article->id,
                        'label' => $article->title,
                        'meta' => collect([optional($article->published_at)->format('M j, Y'), ucfirst((string) $article->status)])->filter()->join(' - '),
                        'url' => route('articles.show', $article),
                        'image' => $this->mediaUrl($article->featured_image),
                        'primaryAction' => 'Read article',
                        'secondaryAction' => 'More articles',
                    ])
                    ->values()
                    ->all(),
            ],
            'classified' => [
                'label' => 'Classifieds',
                'items' => Classified::query()
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->limit(150)
                    ->get(['id', 'title', 'slug', 'city', 'region', 'status', 'published_at', 'featured_image'])
                    ->map(fn (Classified $classified): array => [
                        'type' => 'classified',
                        'id' => $classified->id,
                        'label' => $classified->title,
                        'meta' => collect([$classified->city, $classified->region, ucfirst((string) $classified->status)])->filter()->join(' - '),
                        'url' => route('classifieds.show', $classified),
                        'image' => $this->mediaUrl($classified->featured_image),
                        'primaryAction' => 'View classified',
                        'secondaryAction' => 'Browse classifieds',
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    private function attachmentPresets(): array
    {
        return collect($this->attachmentGroups())
            ->flatMap(fn (array $group): Collection => collect($group['items']))
            ->mapWithKeys(fn (array $item): array => [$item['type'].':'.$item['id'] => $item])
            ->all();
    }

    private function resolveAttachment(?string $type, ?int $id): ?array
    {
        if (! $type || ! $id) {
            return null;
        }

        return $this->attachmentPresets()[$type.':'.$id] ?? null;
    }

    private function mediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }
}
