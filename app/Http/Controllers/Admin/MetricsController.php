<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\CivicFaultReport;
use App\Models\Councillor;
use App\Models\Listing;
use App\Models\MarketingIntegration;
use App\Models\PushCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->user();

        $now = now();
        $recentResolved = CivicFaultReport::query()
            ->whereNotNull('resolved_at')
            ->orderByDesc('resolved_at')
            ->limit(50)
            ->get(['id', 'created_at', 'resolved_at']);

        $resolutionHours = $recentResolved
            ->filter(fn (CivicFaultReport $report) => $report->created_at && $report->resolved_at)
            ->map(fn (CivicFaultReport $report) => (float) $report->created_at->diffInMinutes($report->resolved_at) / 60)
            ->values();

        return response()->json([
            'faults' => [
                'pending' => CivicFaultReport::where('is_approved', false)->count(),
                'approved' => CivicFaultReport::where('is_approved', true)->count(),
                'reported' => CivicFaultReport::where('status', CivicFaultReport::STATUS_REPORTED)->count(),
                'in_progress' => CivicFaultReport::where('status', CivicFaultReport::STATUS_IN_PROGRESS)->count(),
                'resolved' => CivicFaultReport::where('status', CivicFaultReport::STATUS_RESOLVED)->count(),
                'reported_last_hour' => CivicFaultReport::where('created_at', '>=', $now->copy()->subHour())->count(),
                'resolved_last_7d' => CivicFaultReport::whereNotNull('resolved_at')->where('resolved_at', '>=', $now->copy()->subDays(7))->count(),
                'avg_resolution_hours_last_50' => $resolutionHours->count() > 0 ? round($resolutionHours->avg(), 2) : null,
            ],
            'councillors' => [
                'active' => Councillor::where('is_active', true)->count(),
                'inactive' => Councillor::where('is_active', false)->count(),
            ],
            'advertising' => [
                'ads_active' => AdCampaign::where('status', 'active')->count(),
                'ads_ready' => AdCampaign::where('status', 'ready')->count(),
                'push_pending' => PushCampaign::whereNull('sent_at')->count(),
            ],
            'integrations' => [
                'total' => MarketingIntegration::count(),
                'active' => MarketingIntegration::where('status', 'active')->count(),
            ],
            'core' => [
                'listings' => Listing::count(),
                'vouchers' => class_exists(\App\Models\Voucher::class) ? \App\Models\Voucher::count() : 0,
            ],
        ]);
    }
}
