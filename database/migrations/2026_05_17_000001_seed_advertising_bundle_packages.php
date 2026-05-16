<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $advertTypeId = DB::table('package_types')->where('slug', 'advert_package')->value('id');
        $pushTypeId = DB::table('package_types')->where('slug', 'push_campaign')->value('id');

        if ($advertTypeId) {
            $this->package($advertTypeId, 'article-intro-placement-30d', 'Article Intro Placement', 'Appears after the first paragraph on eligible article pages for 30 days.', 'once_off', 30, 300, [
                'entitlement_code' => 'advert_package',
                'placement' => 'in_article_intro',
                'reach' => 'article_pages',
            ], $now);

            $this->package($advertTypeId, 'article-mid-placement-30d', 'Article Mid-Page Placement', 'Appears between article sections on eligible article pages for 30 days.', 'once_off', 30, 250, [
                'entitlement_code' => 'advert_package',
                'placement' => 'in_article_mid',
                'reach' => 'article_pages',
            ], $now);

            $this->package($advertTypeId, 'article-end-placement-30d', 'Article End Placement', 'Appears below article content near related links for 30 days.', 'once_off', 30, 180, [
                'entitlement_code' => 'advert_package',
                'placement' => 'in_article_end',
                'reach' => 'article_pages',
            ], $now);

            $this->package($advertTypeId, 'category-banner-30d', 'Category Banner Placement', 'Banner visibility on selected directory, event, article, or classified sections for 30 days.', 'once_off', 30, 450, [
                'entitlement_code' => 'advert_package',
                'placement' => 'banner',
                'reach' => 'section_pages',
            ], $now);

            $this->package($advertTypeId, 'sitewide-banner-30d', 'Sitewide Banner Placement', 'Highest-reach banner placement visible across the platform for 30 days.', 'once_off', 30, 950, [
                'entitlement_code' => 'advert_package',
                'placement' => 'sitewide_banner',
                'reach' => 'all_pages',
            ], $now);
        }

        if ($pushTypeId) {
            $this->package($pushTypeId, 'push-campaign-city-once', 'City Push Notification', 'One scheduled push notification targeted to the listing city.', 'once_off', 7, 250, [
                'entitlement_code' => 'push_notification',
                'audience_scope' => 'listing_city',
            ], $now);

            $this->package($pushTypeId, 'push-campaign-region-once', 'Regional Push Notification', 'One scheduled push notification targeted to the wider listing region.', 'once_off', 7, 450, [
                'entitlement_code' => 'push_notification',
                'audience_scope' => 'listing_region',
            ], $now);
        }
    }

    public function down(): void
    {
        $slugs = [
            'article-intro-placement-30d',
            'article-mid-placement-30d',
            'article-end-placement-30d',
            'category-banner-30d',
            'sitewide-banner-30d',
            'push-campaign-city-once',
            'push-campaign-region-once',
        ];

        DB::table('package_prices')->whereIn('package_id', function ($query) use ($slugs) {
            $query->select('id')->from('packages')->whereIn('slug', $slugs);
        })->delete();

        DB::table('packages')->whereIn('slug', $slugs)->delete();
    }

    private function package(
        int $typeId,
        string $slug,
        string $name,
        string $description,
        string $billingModel,
        int $durationDays,
        float $amount,
        array $settings,
        mixed $now
    ): void {
        DB::table('packages')->updateOrInsert(
            ['slug' => $slug],
            [
                'package_type_id' => $typeId,
                'name' => $name,
                'description' => $description,
                'billing_model' => $billingModel,
                'is_self_service' => true,
                'duration_days' => $durationDays,
                'status' => 'active',
                'settings_json' => json_encode($settings),
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $packageId = DB::table('packages')->where('slug', $slug)->value('id');

        DB::table('package_prices')->updateOrInsert(
            ['package_id' => $packageId],
            [
                'currency' => 'ZAR',
                'amount' => $amount,
                'vat_inclusive' => true,
                'effective_from' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }
};
