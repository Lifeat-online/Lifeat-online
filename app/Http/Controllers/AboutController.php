<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Setting;
use Illuminate\Contracts\View\View;

class AboutController extends Controller
{
    public function index(): View
    {
        return view('about.index', [
            'contact' => [
                'email' => (string) Setting::getValue('contact.email', 'support@life.local'),
                'phone' => (string) Setting::getValue('contact.phone', '+27 58 000 0000'),
                'region' => (string) Setting::getValue('contact.region', 'Eastern Freestate'),
            ],
            'stats' => [
                'listings' => Listing::published()->count(),
                'events' => Event::published()->count(),
                'articles' => Article::published()->count(),
            ],
        ]);
    }
}
