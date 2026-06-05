<?php

namespace App\Http\Middleware;

use App\Models\MallOrder;
use App\Models\MallProduct;
use App\Models\MallProductCategory;
use App\Models\MallStore;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMallVendorIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $store = $user?->mallStore;

        if (! $store || $store->status !== MallStore::STATUS_ACTIVE) {
            abort(403);
        }

        foreach ($request->route()?->parameters() ?? [] as $value) {
            if ($value instanceof MallOrder && (int) $value->mall_store_id !== (int) $store->id) {
                abort(404);
            }

            if ($value instanceof MallProduct && (int) $value->mall_store_id !== (int) $store->id) {
                abort(404);
            }

            if ($value instanceof MallProductCategory && (int) $value->mall_store_id !== (int) $store->id) {
                abort(404);
            }
        }

        $request->attributes->set('mall_store', $store);

        return $next($request);
    }
}
