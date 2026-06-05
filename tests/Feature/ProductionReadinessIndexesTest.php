<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionReadinessIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_readiness_indexes_are_present(): void
    {
        $expected = [
            'listings' => [
                'pr_listings_status_featured_pub_idx' => ['status', 'is_featured', 'published_at'],
                'pr_listings_status_city_idx' => ['status', 'city'],
                'pr_listings_status_geo_idx' => ['status', 'latitude', 'longitude'],
                'pr_listings_user_status_created_idx' => ['user_id', 'status', 'created_at'],
            ],
            'events' => [
                'pr_events_status_start_idx' => ['status', 'start_at'],
                'pr_events_listing_status_start_idx' => ['listing_id', 'status', 'start_at'],
            ],
            'articles' => [
                'pr_articles_status_published_idx' => ['status', 'published_at'],
                'pr_articles_user_status_pub_idx' => ['user_id', 'status', 'published_at'],
            ],
            'classifieds' => [
                'pr_classifieds_status_published_idx' => ['status', 'published_at'],
                'pr_classifieds_status_city_pub_idx' => ['status', 'city', 'published_at'],
            ],
            'subscriptions' => [
                'pr_subscriptions_status_ends_idx' => ['status', 'ends_at'],
                'pr_subscriptions_sub_status_ends_idx' => ['subscribable_type', 'subscribable_id', 'status', 'ends_at'],
            ],
            'orders' => [
                'pr_orders_status_created_idx' => ['status', 'created_at'],
                'pr_orders_user_status_created_idx' => ['user_id', 'status', 'created_at'],
            ],
            'payments' => [
                'pr_payments_status_created_idx' => ['status', 'created_at'],
                'pr_payments_status_paid_idx' => ['status', 'paid_at'],
            ],
            'ad_campaigns' => [
                'pr_ads_status_published_idx' => ['status', 'published_at'],
                'pr_ads_listing_status_created_idx' => ['listing_id', 'status', 'created_at'],
            ],
            'push_campaigns' => [
                'pr_push_status_schedule_idx' => ['status', 'schedule_at'],
                'pr_push_status_sent_idx' => ['status', 'sent_at'],
            ],
            'vouchers' => [
                'pr_vouchers_status_window_idx' => ['status', 'start_at', 'end_at'],
                'pr_vouchers_listing_status_pub_idx' => ['listing_id', 'status', 'published_at'],
            ],
            'voucher_redemptions' => [
                'pr_voucher_redemptions_voucher_status_claimed_idx' => ['voucher_id', 'status', 'claimed_at'],
            ],
            'notification_logs' => [
                'pr_notifications_channel_status_sent_idx' => ['channel', 'status', 'sent_at'],
                'pr_notifications_type_status_created_idx' => ['notification_type', 'status', 'created_at'],
            ],
            'reviews' => [
                'pr_reviews_listing_status_created_idx' => ['listing_id', 'status', 'created_at'],
            ],
            'categories' => [
                'pr_categories_type_name_idx' => ['type', 'name'],
            ],
        ];

        foreach ($expected as $table => $indexes) {
            foreach ($indexes as $indexName => $columns) {
                $this->assertSame(
                    $columns,
                    $this->indexColumns($table, $indexName),
                    "Expected {$table}.{$indexName} to exist with the reviewed column order."
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function indexColumns(string $table, string $indexName): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $index = collect(DB::select("PRAGMA index_list('{$table}')"))
                ->firstWhere('name', $indexName);

            if (! $index) {
                return [];
            }

            return collect(DB::select("PRAGMA index_info('{$indexName}')"))
                ->sortBy('seqno')
                ->pluck('name')
                ->values()
                ->all();
        }

        if ($driver === 'mysql') {
            return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]))
                ->sortBy('Seq_in_index')
                ->pluck('Column_name')
                ->values()
                ->all();
        }

        if ($driver === 'pgsql') {
            $definition = DB::selectOne(
                "SELECT indexdef FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?",
                [$table, $indexName]
            )?->indexdef;

            if (! is_string($definition) || ! str_contains($definition, '(')) {
                return [];
            }

            preg_match('/\((.*)\)$/', $definition, $matches);

            return collect(explode(',', $matches[1] ?? ''))
                ->map(fn (string $column): string => trim($column, ' "'))
                ->values()
                ->all();
        }

        $this->fail("Unsupported database driver for index verification: {$driver}");
    }
}
