<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class LocalePreferenceService
{
    public const COOKIE_NAME = 'locale';

    public function resolve(Request $request): string
    {
        foreach ([
            $request->user()?->preferred_locale,
            $request->cookie(self::COOKIE_NAME),
            $request->session()->get(self::COOKIE_NAME),
            $this->defaultLocale(),
        ] as $candidate) {
            $locale = $this->normalize(is_string($candidate) ? $candidate : null);

            if ($locale !== null) {
                return $locale;
            }
        }

        return $this->defaultLocale();
    }

    public function remember(Request $request, string $locale, ?User $user = null, bool $saveUser = true): string
    {
        $locale = $this->normalize($locale) ?: $this->defaultLocale();

        $request->session()->put(self::COOKIE_NAME, $locale);
        App::setLocale($locale);

        $user = $user ?: $request->user();

        if ($saveUser && $user && $this->userLocaleColumnExists() && $this->normalize($user->preferred_locale) !== $locale) {
            $user->forceFill(['preferred_locale' => $locale])->save();
        }

        return $locale;
    }

    public function syncAuthenticatedUser(Request $request, User $user): string
    {
        $locale = $this->normalize($user->preferred_locale)
            ?: $this->normalize($request->cookie(self::COOKIE_NAME))
            ?: $this->normalize($request->session()->get(self::COOKIE_NAME))
            ?: $this->defaultLocale();

        return $this->remember($request, $locale, $user);
    }

    public function cookie(string $locale): SymfonyCookie
    {
        return Cookie::make(
            self::COOKIE_NAME,
            $this->normalize($locale) ?: $this->defaultLocale(),
            60 * 24 * 365,
            null,
            null,
            null,
            true,
            false,
            'lax'
        );
    }

    public function normalize(?string $locale): ?string
    {
        $locale = trim((string) $locale);

        if ($locale === '') {
            return null;
        }

        return in_array($locale, $this->supportedLocales(), true) ? $locale : null;
    }

    public function supportedLocales(): array
    {
        return array_keys((array) config('localization.supported', ['en' => []]));
    }

    public function defaultLocale(): string
    {
        $default = (string) config('localization.default', 'en');

        return $this->normalize($default) ?: 'en';
    }

    private function userLocaleColumnExists(): bool
    {
        try {
            return Schema::hasColumn('users', 'preferred_locale');
        } catch (\Throwable) {
            return true;
        }
    }
}
