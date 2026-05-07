<?php

namespace App\Http\Controllers;

use App\Models\Councillor;
use App\Models\CivicFaultReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CivicFaultDataController extends Controller
{
    public function faults(Request $request)
    {
        $query = CivicFaultReport::query()
            ->where('is_approved', true);

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($from = $request->string('from')->toString()) {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to = $request->string('to')->toString()) {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        if ($councillorId = $request->integer('councillor_id')) {
            $query->where('assigned_councillor_id', $councillorId);
        }

        $reports = $query
            ->with(['assignedCouncillor'])
            ->latest()
            ->limit(2500)
            ->get();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $reports->map(function (CivicFaultReport $report) {
                $categoryLabel = CivicFaultReport::categories()[$report->category] ?? $report->category;

                return [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [(float) $report->longitude, (float) $report->latitude],
                    ],
                    'properties' => [
                        'id' => $report->id,
                        'category' => $report->category,
                        'category_label' => $categoryLabel,
                        'severity' => $report->severity,
                        'status' => $report->status,
                        'created_at' => $report->created_at?->toIso8601String(),
                        'councillor' => $report->assignedCouncillor ? [
                            'id' => $report->assignedCouncillor->id,
                            'full_name' => $report->assignedCouncillor->full_name,
                            'phone' => $report->assignedCouncillor->phone,
                            'email' => $report->assignedCouncillor->email,
                            'office_address' => $report->assignedCouncillor->office_address,
                            'portfolios' => $report->assignedCouncillor->portfolios,
                        ] : null,
                    ],
                ];
            })->values(),
        ]);
    }

    public function councillors(Request $request)
    {
        $councillors = Councillor::query()
            ->where('is_active', true)
            ->with(['areas' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('full_name')
            ->get();

        return response()->json([
            'councillors' => $councillors->map(function (Councillor $councillor) {
                return [
                    'id' => $councillor->id,
                    'full_name' => $councillor->full_name,
                    'phone' => $councillor->phone,
                    'email' => $councillor->email,
                    'office_address' => $councillor->office_address,
                    'portfolios' => $councillor->portfolios,
                    'category_responsibilities' => $councillor->category_responsibilities,
                    'areas' => $councillor->areas->map(fn ($area) => [
                        'id' => $area->id,
                        'name' => $area->name,
                        'geojson' => $area->geojson,
                    ])->values(),
                ];
            })->values(),
        ]);
    }
}
