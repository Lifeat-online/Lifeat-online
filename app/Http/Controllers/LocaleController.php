<?php

namespace App\Http\Controllers;

use App\Services\LocalePreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale, LocalePreferenceService $preferences): RedirectResponse
    {
        $supported = array_keys((array) config('localization.supported', []));

        abort_unless(
            in_array($locale, $supported, true) && preg_match('/^[a-z]{2}(-[A-Za-z0-9]{2,8})?$/', $locale) === 1,
            404,
        );

        $locale = $preferences->remember($request, $locale, $request->user());

        return redirect()
            ->back()
            ->withCookie($preferences->cookie($locale));
    }
}
