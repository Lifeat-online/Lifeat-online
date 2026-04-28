<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\PayoutRequest;
use App\Models\Article;
use App\Models\Event;
use App\Models\Listing;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PushCampaign;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WriterApplication;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = Auth::user();
        $supportThreshold = Carbon::now()->addDays(7);

        $data = [
            'user' => $user,
        ];

        // If the user has a staff/admin/writer role, provide the management data
        if ($user->hasRole('admin', 'editor', 'staff', 'support', 'writer')) {
            $data = array_merge($data, [
                'dashboardRoleFlags' => [
                    'canCreateContent' => $user->hasRole('admin', 'editor', 'staff', 'writer'),
                    'isSupport' => $user->hasRole('support'),
                    'isStaffWriter' => $user->hasRole('admin', 'staff', 'writer'),
                    'canReviewApplications' => $user->hasRole('admin', 'editor'),
                ],
                'counts' => [
                    'users' => User::count(),
                    'listings' => Listing::count(),
                    'events' => Event::count(),
                    'articles' => Article::count(),
                    'writerApplications' => WriterApplication::count(),
                ],
                'supportCounts' => [
                    'orders' => Order::count(),
                    'payments' => Payment::count(),
                    'subscriptions' => Subscription::count(),
                    'notifications' => NotificationLog::count(),
                    'refunds' => PaymentRefund::count(),
                    'failedPayments' => Payment::where('status', 'failed')->count(),
                    'pendingNotifications' => NotificationLog::whereIn('status', ['pending', 'queued', 'failed'])->count(),
                    'expiringSubscriptions' => Subscription::where('status', 'active')
                        ->whereNotNull('ends_at')
                        ->whereBetween('ends_at', [Carbon::now(), $supportThreshold])
                        ->count(),
                    'pushDeliveries' => NotificationLog::where('channel', 'push')->count(),
                    'pendingPushCampaigns' => PushCampaign::whereNull('sent_at')
                        ->whereIn('status', ['active', 'scheduled'])
                        ->count(),
                    'adCampaignsPendingApproval' => AdCampaign::where('status', 'ready')->count(),
                    'adCampaignsActive' => AdCampaign::where('status', 'active')->count(),
                    'pendingPayoutRequests' => PayoutRequest::whereIn('status', PayoutRequest::activeStatuses())->count(),
                ],
                'supportQueues' => [
                    'failedPayments' => Payment::with(['order.user'])
                        ->where('status', 'failed')
                        ->latest()
                        ->limit(5)
                        ->get(),
                    'pendingNotifications' => NotificationLog::query()
                        ->whereIn('status', ['pending', 'queued', 'failed'])
                        ->latest('sent_at')
                        ->limit(5)
                        ->get(),
                    'expiringSubscriptions' => Subscription::with(['user', 'package'])
                        ->where('status', 'active')
                        ->whereNotNull('ends_at')
                        ->whereBetween('ends_at', [Carbon::now(), $supportThreshold])
                        ->orderBy('ends_at')
                        ->limit(5)
                        ->get(),
                ],
                'latestListings' => Listing::latest()->limit(5)->get(),
                'latestEvents' => Event::latest()->limit(5)->get(),
                'latestArticles' => Article::latest()->limit(5)->get(),
                'latestWriterApplications' => WriterApplication::latest('submitted_at')->limit(5)->get(),
            ]);
        }

        return view('dashboard', $data);
    }
}
