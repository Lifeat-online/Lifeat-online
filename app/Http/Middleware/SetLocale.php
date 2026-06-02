<?php

namespace App\Http\Middleware;

use App\Services\LocalePreferenceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $preferences = app(LocalePreferenceService::class);

        $locale = $preferences->resolve($request);
        $shouldBackfillProfile = $request->user()
            && $preferences->normalize($request->user()->preferred_locale) === null;

        $preferences->remember($request, $locale, $request->user(), $shouldBackfillProfile);

        $response = $next($request);
        $responseLocale = $preferences->normalize(App::getLocale()) ?: $locale;

        if ($request->cookie(LocalePreferenceService::COOKIE_NAME) !== $responseLocale) {
            $response->headers->setCookie($preferences->cookie($responseLocale));
        }

        return $response;
    }
}
