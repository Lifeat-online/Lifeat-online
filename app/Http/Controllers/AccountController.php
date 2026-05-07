<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Classified;
use App\Models\Invoice;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $orders = Order::with(['payments', 'invoices', 'items.package'])
            ->where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get();

        $subscriptions = Subscription::with(['package', 'subscribable'])
            ->where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get();

        $invoices = Invoice::with('order')
            ->whereHas('order', fn ($query) => $query->where('user_id', $user->id))
            ->latest()
            ->limit(10)
            ->get();

        $listings = Listing::with('activeSubscription')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(6)
            ->get();

        $articles = Article::with('wordLedger')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(6)
            ->get();

        $classifieds = Classified::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(6)
            ->get();

        return view('account.index', [
            'user' => $user,
            'orders' => $orders,
            'invoices' => $invoices,
            'subscriptions' => $subscriptions,
            'listings' => $listings,
            'articles' => $articles,
            'classifieds' => $classifieds,
            'accountStats' => [
                'orders' => $orders->count(),
                'invoices' => $invoices->count(),
                'subscriptions' => $subscriptions->count(),
                'active_subscriptions' => $subscriptions->filter->isActive()->count(),
                'listings' => $listings->count(),
                'articles' => $articles->count(),
                'classifieds' => $classifieds->count(),
            ],
            'quickLinks' => collect([
                ['label' => 'Edit profile', 'description' => 'Update your account details and password.', 'route' => 'profile.edit', 'visible' => true],
                ['label' => 'Open dashboard', 'description' => 'Go to your authenticated home area.', 'route' => 'dashboard', 'visible' => true],
                ['label' => 'Start listing', 'description' => 'Create a new business listing starter.', 'route' => 'add-listing.index', 'visible' => true],
                ['label' => 'My listings', 'description' => 'Track business listing status, package progress, and renewals.', 'route' => 'account.listings.index', 'visible' => true],
                ['label' => 'Advertising dashboard', 'description' => 'Manage ads, push campaigns, and marketing integrations.', 'route' => 'account.advertising.index', 'visible' => true],
                ['label' => 'Browse packages', 'description' => 'Review package and checkout options.', 'route' => 'checkout.index', 'visible' => true],
                ['label' => 'My invoices', 'description' => 'Review invoice history tied to your orders.', 'route' => 'account.invoices.index', 'visible' => true],
                ['label' => 'Submission history', 'description' => 'Track statuses and feedback across listings, classifieds, and articles.', 'route' => 'account.submissions.index', 'visible' => true],
                ['label' => 'My classifieds', 'description' => 'Submit and track your classifieds moderation status.', 'route' => 'classifieds.manage.index', 'visible' => true],
                ['label' => 'My submissions', 'description' => 'Manage your writer article submissions.', 'route' => 'writer.articles.index', 'visible' => $user->hasRole('writer')],
                ['label' => 'My earnings', 'description' => 'Review writer earnings and payment progress.', 'route' => 'writer.earnings.index', 'visible' => $user->hasRole('writer')],
                ['label' => 'Admin dashboard', 'description' => 'Open operations, finance, and workflow tools.', 'route' => 'admin.dashboard', 'visible' => $user->hasRole('admin', 'editor', 'staff', 'support')],
            ])->where('visible', true)->values(),
        ]);
    }
}
