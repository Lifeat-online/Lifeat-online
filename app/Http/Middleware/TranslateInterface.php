<?php

namespace App\Http\Middleware;

use App\Services\PlatformInterfaceTranslationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TranslateInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $locale = app()->getLocale();

        if ($locale === (string) config('localization.default', 'en') || ! method_exists($response, 'getContent')) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $translations = app(PlatformInterfaceTranslationService::class)->translationsFor($locale);

        if ($translations === []) {
            return $response;
        }

        $html = (string) $response->getContent();
        $response->setContent($this->translateHtml($html, $translations));

        return $response;
    }

    private function translateHtml(string $html, array $translations): string
    {
        $chunks = preg_split('/(<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (! is_array($chunks)) {
            return $html;
        }

        return collect($chunks)
            ->map(function (string $chunk) use ($translations): string {
                if (preg_match('/^<(script|style)\b/i', $chunk)) {
                    return $chunk;
                }

                $chunk = preg_replace_callback('/>([^<>]+)</u', function (array $matches) use ($translations): string {
                    $translated = $this->translateTextNode($matches[1], $translations);

                    if ($translated === null) {
                        return $matches[0];
                    }

                    return '>'.$translated.'<';
                }, $chunk) ?? $chunk;

                return preg_replace_callback('/\b(placeholder|aria-label|title|alt|value)\s*=\s*(["\'])([^"\']+)\2/u', function (array $matches) use ($translations): string {
                    $source = html_entity_decode(trim($matches[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    if (! isset($translations[$source])) {
                        return $matches[0];
                    }

                    return $matches[1].'='.$matches[2].e($translations[$source]).$matches[2];
                }, $chunk) ?? $chunk;
            })
            ->implode('');
    }

    private function translateTextNode(string $text, array $translations): ?string
    {
        $source = html_entity_decode(trim(preg_replace('/\s+/u', ' ', $text) ?? $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($source === '') {
            return null;
        }

        $prefix = preg_match('/^\s/u', $text) ? ' ' : '';
        $suffix = preg_match('/\s$/u', $text) ? ' ' : '';

        if (isset($translations[$source])) {
            return $prefix.e($translations[$source]).$suffix;
        }

        $translated = $this->translateDynamicPrefix($source, $translations);

        return $translated === null ? null : $prefix.e($translated).$suffix;
    }

    private function translateDynamicPrefix(string $source, array $translations): ?string
    {
        foreach ($this->prefixTranslations($translations) as $english => $translated) {
            if (str_starts_with($source, $english.' ')) {
                return $translated.mb_substr($source, mb_strlen($english));
            }
        }

        return null;
    }

    private function prefixTranslations(array $translations): array
    {
        return collect($translations)
            ->filter(fn (string $translated, string $source): bool => str_ends_with($source, ':') || str_word_count($source) >= 2)
            ->sortKeysUsing(fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a))
            ->all();
    }
}
