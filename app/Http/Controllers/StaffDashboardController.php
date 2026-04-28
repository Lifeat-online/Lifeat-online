<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleWordLedger;
use App\Models\Event;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StaffDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('staff.dashboard', [
            'counts' => [
                'listings' => Listing::where('user_id', $user->id)->count(),
                'events' => Event::where('user_id', $user->id)->count(),
                'articles' => Article::where('user_id', $user->id)->count(),
            ],
            'earnings' => [
                'pending' => (float) ArticleWordLedger::where('writer_user_id', $user->id)->where('status', 'pending')->sum('gross_amount'),
                'batched' => (float) ArticleWordLedger::where('writer_user_id', $user->id)->where('status', 'batched')->sum('gross_amount'),
                'paid' => (float) ArticleWordLedger::where('writer_user_id', $user->id)->where('status', 'paid')->sum('gross_amount'),
            ],
            'latest' => [
                'listings' => Listing::where('user_id', $user->id)->latest()->limit(5)->get(),
                'events' => Event::where('user_id', $user->id)->latest()->limit(5)->get(),
                'articles' => Article::where('user_id', $user->id)->latest()->limit(5)->get(),
            ]
        ]);
    }
}
