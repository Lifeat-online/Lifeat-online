<?php

namespace App\Http\Controllers;

use App\Services\LocalePreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale, LocalePreferenceService $preferences): RedirectResponse
    {
        abort_unless(array_key_exists($locale, (array) config('localization.supported')), 404);

        $locale = $preferences->remember($request, $locale, $request->user());

        return redirect()
            ->back()
            ->withCookie($preferences->cookie($locale));
    }
}
