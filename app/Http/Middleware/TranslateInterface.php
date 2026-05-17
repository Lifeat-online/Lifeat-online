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
                    $source = html_entity_decode(trim(preg_replace('/\s+/u', ' ', $matches[1]) ?? $matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    if (! isset($translations[$source])) {
                        return $matches[0];
                    }

                    $prefix = preg_match('/^\s/u', $matches[1]) ? ' ' : '';
                    $suffix = preg_match('/\s$/u', $matches[1]) ? ' ' : '';

                    return '>'.$prefix.e($translations[$source]).$suffix.'<';
                }, $chunk) ?? $chunk;

                return preg_replace_callback('/\b(placeholder|aria-label|title|alt)\s*=\s*"([^"{]+)"/u', function (array $matches) use ($translations): string {
                    $source = html_entity_decode(trim($matches[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    if (! isset($translations[$source])) {
                        return $matches[0];
                    }

                    return $matches[1].'="'.e($translations[$source]).'"';
                }, $chunk) ?? $chunk;
            })
            ->implode('');
    }
}
