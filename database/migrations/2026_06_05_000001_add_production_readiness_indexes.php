<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->index(['status', 'is_featured', 'published_at'], 'pr_listings_status_featured_pub_idx');
            $table->index(['status', 'city'], 'pr_listings_status_city_idx');
            $table->index(['status', 'region'], 'pr_listings_status_region_idx');
            $table->index(['status', 'latitude', 'longitude'], 'pr_listings_status_geo_idx');
            $table->index(['user_id', 'status', 'created_at'], 'pr_listings_user_status_created_idx');
            $table->index(['registered_by_user_id', 'status'], 'pr_listings_registered_status_idx');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->index(['status', 'start_at'], 'pr_events_status_start_idx');
            $table->index(['status', 'city', 'start_at'], 'pr_events_status_city_start_idx');
            $table->index(['status', 'region', 'start_at'], 'pr_events_status_region_start_idx');
            $table->index(['listing_id', 'status', 'start_at'], 'pr_events_listing_status_start_idx');
            $table->index(['active_subscription_id', 'status'], 'pr_events_active_sub_status_idx');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->index(['status', 'published_at'], 'pr_articles_status_published_idx');
            $table->index(['user_id', 'status', 'published_at'], 'pr_articles_user_status_pub_idx');
        });

        Schema::table('classifieds', function (Blueprint $table) {
            $table->index(['status', 'published_at'], 'pr_classifieds_status_published_idx');
            $table->index(['status', 'city', 'published_at'], 'pr_classifieds_status_city_pub_idx');
            $table->index(['user_id', 'status', 'published_at'], 'pr_classifieds_user_status_pub_idx');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['status', 'ends_at'], 'pr_subscriptions_status_ends_idx');
            $table->index(['status', 'renews_at'], 'pr_subscriptions_status_renews_idx');
            $table->index(['user_id', 'status', 'ends_at'], 'pr_subscriptions_user_status_ends_idx');
            $table->index(['subscribable_type', 'subscribable_id', 'status', 'ends_at'], 'pr_subscriptions_sub_status_ends_idx');
            $table->index(['payment_id', 'status'], 'pr_subscriptions_payment_status_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'pr_orders_status_created_idx');
            $table->index(['user_id', 'status', 'created_at'], 'pr_orders_user_status_created_idx');
            $table->index(['renewed_subscription_id', 'status'], 'pr_orders_renewed_sub_status_idx');
            $table->index(['referred_by_user_id', 'status', 'created_at'], 'pr_orders_referrer_status_created_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'pr_payments_status_created_idx');
            $table->index(['status', 'paid_at'], 'pr_payments_status_paid_idx');
            $table->index(['user_id', 'status', 'created_at'], 'pr_payments_user_status_created_idx');
            $table->index(['provider', 'status', 'created_at'], 'pr_payments_provider_status_created_idx');
        });

        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->index(['payment_id', 'status', 'attempted_at'], 'pr_payment_attempts_payment_status_at_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'pr_invoices_status_created_idx');
            $table->index(['order_id', 'status'], 'pr_invoices_order_status_idx');
        });

        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->index(['status', 'published_at'], 'pr_ads_status_published_idx');
            $table->index(['listing_id', 'status', 'created_at'], 'pr_ads_listing_status_created_idx');
            $table->index(['user_id', 'status', 'created_at'], 'pr_ads_user_status_created_idx');
            $table->index(['active_subscription_id', 'status'], 'pr_ads_active_sub_status_idx');
        });

        Schema::table('push_campaigns', function (Blueprint $table) {
            $table->index(['status', 'schedule_at'], 'pr_push_status_schedule_idx');
            $table->index(['status', 'sent_at'], 'pr_push_status_sent_idx');
            $table->index(['listing_id', 'status', 'created_at'], 'pr_push_listing_status_created_idx');
            $table->index(['user_id', 'status', 'created_at'], 'pr_push_user_status_created_idx');
            $table->index(['active_subscription_id', 'status'], 'pr_push_active_sub_status_idx');
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->index(['status', 'published_at'], 'pr_vouchers_status_published_idx');
            $table->index(['status', 'start_at', 'end_at'], 'pr_vouchers_status_window_idx');
            $table->index(['listing_id', 'status', 'published_at'], 'pr_vouchers_listing_status_pub_idx');
            $table->index(['created_by_user_id', 'status', 'created_at'], 'pr_vouchers_creator_status_created_idx');
        });

        Schema::table('voucher_redemptions', function (Blueprint $table) {
            $table->index(['voucher_id', 'status', 'claimed_at'], 'pr_voucher_redemptions_voucher_status_claimed_idx');
            $table->index(['status', 'consumed_at'], 'pr_voucher_redemptions_status_consumed_idx');
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'pr_notifications_status_created_idx');
            $table->index(['channel', 'status', 'sent_at'], 'pr_notifications_channel_status_sent_idx');
            $table->index(['notification_type', 'status', 'created_at'], 'pr_notifications_type_status_created_idx');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index(['listing_id', 'status', 'created_at'], 'pr_reviews_listing_status_created_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['type', 'name'], 'pr_categories_type_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('pr_categories_type_name_idx');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('pr_reviews_listing_status_created_idx');
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex('pr_notifications_status_created_idx');
            $table->dropIndex('pr_notifications_channel_status_sent_idx');
            $table->dropIndex('pr_notifications_type_status_created_idx');
        });

        Schema::table('voucher_redemptions', function (Blueprint $table) {
            $table->dropIndex('pr_voucher_redemptions_voucher_status_claimed_idx');
            $table->dropIndex('pr_voucher_redemptions_status_consumed_idx');
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex('pr_vouchers_status_published_idx');
            $table->dropIndex('pr_vouchers_status_window_idx');
            $table->dropIndex('pr_vouchers_listing_status_pub_idx');
            $table->dropIndex('pr_vouchers_creator_status_created_idx');
        });

        Schema::table('push_campaigns', function (Blueprint $table) {
            $table->dropIndex('pr_push_status_schedule_idx');
            $table->dropIndex('pr_push_status_sent_idx');
            $table->dropIndex('pr_push_listing_status_created_idx');
            $table->dropIndex('pr_push_user_status_created_idx');
            $table->dropIndex('pr_push_active_sub_status_idx');
        });

        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropIndex('pr_ads_status_published_idx');
            $table->dropIndex('pr_ads_listing_status_created_idx');
            $table->dropIndex('pr_ads_user_status_created_idx');
            $table->dropIndex('pr_ads_active_sub_status_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('pr_invoices_status_created_idx');
            $table->dropIndex('pr_invoices_order_status_idx');
        });

        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->dropIndex('pr_payment_attempts_payment_status_at_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('pr_payments_status_created_idx');
            $table->dropIndex('pr_payments_status_paid_idx');
            $table->dropIndex('pr_payments_user_status_created_idx');
            $table->dropIndex('pr_payments_provider_status_created_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('pr_orders_status_created_idx');
            $table->dropIndex('pr_orders_user_status_created_idx');
            $table->dropIndex('pr_orders_renewed_sub_status_idx');
            $table->dropIndex('pr_orders_referrer_status_created_idx');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('pr_subscriptions_status_ends_idx');
            $table->dropIndex('pr_subscriptions_status_renews_idx');
            $table->dropIndex('pr_subscriptions_user_status_ends_idx');
            $table->dropIndex('pr_subscriptions_sub_status_ends_idx');
            $table->dropIndex('pr_subscriptions_payment_status_idx');
        });

        Schema::table('classifieds', function (Blueprint $table) {
            $table->dropIndex('pr_classifieds_status_published_idx');
            $table->dropIndex('pr_classifieds_status_city_pub_idx');
            $table->dropIndex('pr_classifieds_user_status_pub_idx');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex('pr_articles_status_published_idx');
            $table->dropIndex('pr_articles_user_status_pub_idx');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('pr_events_status_start_idx');
            $table->dropIndex('pr_events_status_city_start_idx');
            $table->dropIndex('pr_events_status_region_start_idx');
            $table->dropIndex('pr_events_listing_status_start_idx');
            $table->dropIndex('pr_events_active_sub_status_idx');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('pr_listings_status_featured_pub_idx');
            $table->dropIndex('pr_listings_status_city_idx');
            $table->dropIndex('pr_listings_status_region_idx');
            $table->dropIndex('pr_listings_status_geo_idx');
            $table->dropIndex('pr_listings_user_status_created_idx');
            $table->dropIndex('pr_listings_registered_status_idx');
        });
    }
};
