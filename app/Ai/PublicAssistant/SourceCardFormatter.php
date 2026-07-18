<?php

namespace App\Ai\PublicAssistant;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SourceCardFormatter
{
    public function format(Collection $sources, array $intent, string $question, array $context, string $locale, Closure $translate): Collection
    {
        return $sources->map(function (array $source) use ($intent, $question, $context, $locale, $translate): array {
            unset($source['keywords']);
            $source['label'] = $this->sourceLabel((string) ($source['type'] ?? 'source'), $locale, $translate);
            $source['meta'] = collect($source['meta'] ?? [])->reject(fn ($value): bool => $value === null || $value === '' || $value === [])->all();
            $source['actions'] = $this->sourceActions($source, $intent, $question, $context, $locale, $translate);

            return $source;
        });
    }

    public function answerActions(array $intent, Collection $sources, string $question, array $context, array $search, string $locale, Closure $translate): array
    {
        $actions = [];
        $first = $sources->first();
        if (is_array($first) && filled($first['url'] ?? null)) {
            $actions[] = $this->action($translate('action.open_best_match', $locale), (string) $first['url'], 'primary');
        }
        $actions[] = $this->action($translate('action.full_search', $locale), route('search.index', array_filter(['q' => $question, 'loc' => $search['location'] ?? null])), 'search');

        foreach (match ($intent['key'] ?? 'general') {
            'business_owner' => [
                $this->action($translate('action.my_listings', $locale), route('account.listings.index'), 'secondary'),
                $this->action($translate('action.advertise', $locale), route('advertise.index'), 'secondary'),
            ],
            'business_search' => [$this->action($translate('action.directory', $locale), route('directory.index', array_filter(['q' => implode(' ', $search['base_terms'] ?? []), 'location' => $search['location'] ?? null])), 'secondary')],
            'website_project' => [
                $this->action($translate('action.developers', $locale), route('directory.index', array_filter(['q' => 'developer website', 'location' => $search['location'] ?? null])), 'secondary'),
                $this->action($translate('action.contact_life', $locale), route('contact.index'), 'secondary'),
            ],
            'accommodation_search' => [$this->action($translate('action.accommodation', $locale), route('directory.index', array_filter(['q' => 'hotel b&b accommodation', 'location' => $search['location'] ?? null])), 'secondary')],
            'event_discovery' => [$this->action($translate('action.events', $locale), route('events.index', array_filter(['q' => $question, 'location' => $search['location'] ?? null])), 'secondary')],
            'voucher_discovery' => [$this->action($translate('action.vouchers', $locale), route('vouchers.index'), 'secondary')],
            'classified_discovery' => [
                $this->action($translate('action.classifieds', $locale), route('classifieds.index'), 'secondary'),
                $this->action($translate('action.post_classified', $locale), route('classifieds.manage.create'), 'secondary'),
            ],
            'fault_reporting' => [
                $this->action($translate('action.fault_map', $locale), route('faults.index'), 'secondary'),
                $this->action($translate('action.report_fault', $locale), route('faults.report.create'), 'secondary'),
            ],
            'transport_help' => [$this->action($translate('action.transport', $locale), route('transport.index'), 'secondary')],
            'article_lookup' => [$this->action($translate('action.articles', $locale), route('articles.index', array_filter(['q' => implode(' ', $search['base_terms'] ?? [])])), 'secondary')],
            'support' => [$this->action($translate('action.contact_life', $locale), route('contact.index'), 'secondary')],
            default => [],
        } as $action) {
            $actions[] = $action;
        }
        if (($context['page_type'] ?? '') === 'account_listing_workspace') {
            $actions[] = $this->action($translate('action.listing_workspace', $locale), route('account.listings.index'), 'secondary');
        }

        return collect($actions)->unique(fn (array $action): string => $action['label'].'|'.$action['url'])->take(4)->values()->all();
    }

    private function sourceActions(array $source, array $intent, string $question, array $context, string $locale, Closure $translate): array
    {
        $actions = [];
        if (filled($source['url'] ?? null)) {
            $actions[] = $this->action($translate('action.view', $locale), (string) $source['url'], 'primary');
        }
        if (filled(data_get($source, 'meta.phone'))) {
            $phone = preg_replace('/[^\d+]/', '', (string) data_get($source, 'meta.phone'));
            if ($phone !== '') {
                $actions[] = $this->action($translate('action.call', $locale), 'tel:'.$phone, 'phone');
            }
        }
        if (filled(data_get($source, 'meta.website'))) {
            $actions[] = $this->action($translate('action.website', $locale), (string) data_get($source, 'meta.website'), 'external', true);
        }
        if ($mapQuery = $this->mapQueryForSource($source)) {
            $actions[] = $this->action($translate('action.directions', $locale), 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($mapQuery), 'directions', true);
        }
        if (($source['type'] ?? '') === 'fault') {
            $actions[] = $this->action($translate('action.report_fault', $locale), route('faults.report.create'), 'secondary');
        }
        if (($source['id'] ?? '') === 'guide:business-owner') {
            $actions[] = $this->action($translate('action.open_listings', $locale), route('account.listings.index'), 'secondary');
        }

        return collect($actions)->unique(fn (array $action): string => $action['label'].'|'.$action['url'])->take(4)->values()->all();
    }

    private function sourceLabel(string $type, string $locale, Closure $translate): string
    {
        $key = ['business' => 'label.business', 'event' => 'label.event', 'article' => 'label.article', 'voucher' => 'label.voucher', 'classified' => 'label.classified', 'fault' => 'label.fault', 'guide' => 'label.guide', 'page' => 'label.page', 'start' => 'label.start', 'packages' => 'label.packages'][$type] ?? null;

        return $key ? $translate($key, $locale) : Str::headline($type);
    }

    private function mapQueryForSource(array $source): ?string
    {
        $location = (string) ($source['location'] ?? data_get($source, 'meta.address', ''));

        return $location === '' || in_array(($source['type'] ?? ''), ['article', 'guide', 'page'], true) ? null : trim(($source['title'] ?? '').' '.$location);
    }

    private function action(string $label, string $url, string $kind = 'link', bool $external = false): array
    {
        return compact('label', 'url', 'kind', 'external');
    }
}
