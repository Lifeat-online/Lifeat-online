<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WriterApplication;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerLookupController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->string('q'));

        $customers = User::query()
            ->withCount(['orders', 'payments', 'subscriptions', 'listings', 'events', 'articles'])
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('username', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhereHas('orders', fn ($orders) => $orders->where('order_number', 'like', "%{$query}%"))
                        ->orWhereHas('payments', fn ($payments) => $payments->where('provider_transaction_id', 'like', "%{$query}%"))
                        ->orWhereHas('writerApplications', function ($applications) use ($query) {
                            $applications->where('email', 'like', "%{$query}%")
                                ->orWhere('phone', 'like', "%{$query}%");
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'filters' => ['q' => $query],
        ]);
    }

    public function show(User $user): View
    {
        $request = request();

        $user->load([
            'orders.payments',
            'orders.invoices',
            'payments.order',
            'subscriptions.package',
            'subscriptions.subscribable',
            'listings.activeSubscription',
            'events.listing',
            'articles.wordLedger',
            'writerApplications',
        ]);

        $timelineFilter = (string) $request->string('timeline_filter', 'all');
        $timelineAction = trim((string) $request->string('timeline_action'));
        $timelineFrom = (string) $request->string('timeline_from');
        $timelineTo = (string) $request->string('timeline_to');
        $timelineFromDate = $timelineFrom !== '' ? Carbon::parse($timelineFrom)->startOfDay() : null;
        $timelineToDate = $timelineTo !== '' ? Carbon::parse($timelineTo)->endOfDay() : null;

        $timelineSubjects = [
            User::class => [$user->id],
            Order::class => $user->orders->modelKeys(),
            Payment::class => $user->payments->modelKeys(),
            Subscription::class => $user->subscriptions->modelKeys(),
            Listing::class => $user->listings->modelKeys(),
            Event::class => $user->events->modelKeys(),
            Article::class => $user->articles->modelKeys(),
            WriterApplication::class => $user->writerApplications->modelKeys(),
        ];

        $supportTimeline = AuditLog::with('actor')
            ->where(function ($query) use ($timelineSubjects, $user) {
                foreach ($timelineSubjects as $type => $ids) {
                    if ($ids === []) {
                        continue;
                    }

                    $query->orWhere(function ($inner) use ($type, $ids) {
                        $inner->where('subject_type', $type)
                            ->whereIn('subject_id', $ids);
                    });
                }

                $query->orWhere('actor_user_id', $user->id);
            })
            ->when($timelineFilter !== 'all', function ($query) use ($timelineFilter, $user) {
                match ($timelineFilter) {
                    'notes' => $query->where('action', 'support.note_added'),
                    'finance' => $query->where(function ($inner) {
                        $inner->where('action', 'like', 'payment.%')
                            ->orWhere('action', 'like', 'subscription.%');
                    }),
                    'content' => $query->whereIn('subject_type', [
                        Listing::class,
                        Event::class,
                        Article::class,
                        WriterApplication::class,
                    ]),
                    'customer' => $query->where(function ($inner) use ($user) {
                        $inner->where('subject_type', User::class)
                            ->where('subject_id', $user->id);
                    }),
                    default => $query,
                };
            })
            ->when($timelineAction !== '', function ($query) use ($timelineAction) {
                $query->where('action', 'like', "%{$timelineAction}%");
            })
            ->when($timelineFromDate !== null, function ($query) use ($timelineFromDate) {
                $query->where('created_at', '>=', $timelineFromDate);
            })
            ->when($timelineToDate !== null, function ($query) use ($timelineToDate) {
                $query->where('created_at', '<=', $timelineToDate);
            })
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.customers.show', [
            'customer' => $user,
            'supportTimeline' => $supportTimeline,
            'summary' => [
                'orders' => $user->orders->count(),
                'payments' => $user->payments->count(),
                'subscriptions' => $user->subscriptions->count(),
                'active_subscriptions' => $user->subscriptions->filter->isActive()->count(),
                'listings' => $user->listings->count(),
                'events' => $user->events->count(),
                'articles' => $user->articles->count(),
            ],
            'timelineFilters' => [
                'timeline_filter' => $timelineFilter,
                'timeline_action' => $timelineAction,
                'timeline_from' => $timelineFrom,
                'timeline_to' => $timelineTo,
            ],
        ]);
    }

    public function storeNote(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        AuditLog::create([
            'actor_user_id' => $request->user()?->id,
            'action' => 'support.note_added',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'before_json' => [],
            'after_json' => [
                'note' => trim($validated['note']),
                'visibility' => 'internal',
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.customers.show', $user)->with('status', 'Support note added.');
    }
}
