<?php

namespace App\Http\Controllers\Mall;

use App\Http\Controllers\Controller;
use App\Models\MallStore;
use App\Models\MallStoreCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MallController extends Controller
{
    public function index(Request $request): View
    {
        $categories = MallStoreCategory::query()
            ->withCount(['stores' => fn ($query) => $query->active()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $stores = MallStore::query()
            ->active()
            ->with([
                'categories',
                'products' => fn ($query) => $query
                    ->active()
                    ->featured()
                    ->orderBy('name'),
            ])
            ->withCount(['products' => fn ($query) => $query->active()])
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->whereHas('categories', fn ($categoryQuery) => $categoryQuery->where('slug', $request->string('category')));
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('name', 'like', $term)
                        ->orWhere('tagline', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('mall.index', compact('categories', 'stores'));
    }
}
