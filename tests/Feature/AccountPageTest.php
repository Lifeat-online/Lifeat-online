<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\AdCampaign;
use App\Models\Category;
use App\Models\Classified;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\PackageType;
use App\Models\Payment;
use App\Models\PushCampaign;
use App\Models\Review;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_page_shows_self_service_hub_for_authenticated_user(): void
    {
        $user = User::factory()->create([
            'role' => 'business_owner',
            'name' => 'Lerato Dlamini',
        ]);

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertOk();
        $response->assertSee('Account Hub');
        $response->assertSee('Lerato Dlamini');
        $response->assertSee('Quick Links');
        $response->assertSee('Edit profile');
        $response->assertSee('Start listing');
        $response->assertSee('Browse packages');
        $response->assertSee('My invoices');
        $response->assertSee('Submission history');
        $response->assertSee('No listings yet.');
        $response->assertSee('No article submissions yet.');
    }

    public function test_account_page_shows_writer_specific_links_for_writer_accounts(): void
    {
        $writer = User::factory()->create([
            'role' => 'writer',
        ]);

        $response = $this->actingAs($writer)->get(route('account.index'));

        $response->assertOk();
        $response->assertSee('My submissions');
        $response->assertSee('My earnings');
    }

    public function test_account_invoice_pages_show_only_owned_invoices(): void
    {
        $owner = User::factory()->create([
            'role' => 'business_owner',
        ]);
        $otherUser = User::factory()->create();

        $ownedOrder = Order::create([
            'user_id' => $owner->id,
            'order_number' => 'ORD-ACC-1',
            'status' => 'paid',
            'currency' => 'ZAR',
            'subtotal' => 500,
            'vat_amount' => 0,
            'total' => 500,
            'placed_at' => now(),
        ]);

        $ownedInvoice = Invoice::create([
            'order_id' => $ownedOrder->id,
            'invoice_number' => 'INV-ACC-1',
            'invoice_prefix_snapshot' => 'LIFE',
            'status' => 'issued',
            'currency' => 'ZAR',
            'subtotal' => 500,
            'vat_amount' => 0,
            'total' => 500,
            'issued_at' => now(),
        ]);

        $foreignOrder = Order::create([
            'user_id' => $otherUser->id,
            'order_number' => 'ORD-ACC-2',
            'status' => 'paid',
            'currency' => 'ZAR',
            'subtotal' => 650,
            'vat_amount' => 0,
            'total' => 650,
            'placed_at' => now(),
        ]);

        $foreignInvoice = Invoice::create([
            'order_id' => $foreignOrder->id,
            'invoice_number' => 'INV-ACC-2',
            'invoice_prefix_snapshot' => 'LIFE',
            'status' => 'issued',
            'currency' => 'ZAR',
            'subtotal' => 650,
            'vat_amount' => 0,
            'total' => 650,
            'issued_at' => now(),
        ]);

        $indexResponse = $this->actingAs($owner)->get(route('account.invoices.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('My Invoices');
        $indexResponse->assertSee('INV-ACC-1');
        $indexResponse->assertDontSee('INV-ACC-2');

        $showResponse = $this->actingAs($owner)->get(route('account.invoices.show', $ownedInvoice));
        $showResponse->assertOk();
        $showResponse->assertSee('Invoice INV-ACC-1');
        $showResponse->assertSee('Order:');

        $forbiddenResponse = $this->actingAs($owner)->get(route('account.invoices.show', $foreignInvoice));
        $forbiddenResponse->assertForbidden();
    }

    public function test_account_submission_history_shows_owned_items_and_supports_type_filter(): void
    {
        $user = User::factory()->create([
            'role' => 'writer',
        ]);

        Listing::create([
            'user_id' => $user->id,
            'source_channel' => 'self_service',
            'title' => 'History Listing',
            'slug' => 'history-listing',
            'status' => 'draft',
        ]);

        Classified::create([
            'user_id' => $user->id,
            'title' => 'History Classified',
            'slug' => 'history-classified',
            'currency' => 'ZAR',
            'status' => Classified::STATUS_PENDING,
            'moderation_notes' => 'Waiting on moderation review.',
        ]);

        Article::create([
            'user_id' => $user->id,
            'title' => 'History Article',
            'slug' => 'history-article',
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('account.submissions.index'));
        $response->assertOk();
        $response->assertSee('Submission History');
        $response->assertSee('History Listing');
        $response->assertSee('History Classified');
        $response->assertSee('History Article');
        $response->assertSee('Waiting on moderation review.');

        $classifiedOnlyResponse = $this->actingAs($user)->get(route('account.submissions.index', [
            'type' => 'classified',
        ]));
        $classifiedOnlyResponse->assertOk();
        $classifiedOnlyResponse->assertSee('History Classified');
        $classifiedOnlyResponse->assertDontSee('History Listing');
        $classifiedOnlyResponse->assertDontSee('History Article');
    }

    public function test_account_listing_pages_show_owned_listing_workspace_only(): void
    {
        $owner = User::factory()->create([
            'role' => 'business_owner',
        ]);
        $otherUser = User::factory()->create();

        $listing = Listing::create([
            'user_id' => $owner->id,
            'source_channel' => 'self_service',
            'title' => 'Owner Workspace Listing',
            'slug' => 'owner-workspace-listing',
            'city' => 'Bethlehem',
            'status' => 'published',
        ]);

        $packageType = PackageType::firstOrCreate([
            'slug' => 'business_directory',
        ], [
            'name' => 'Business Directory',
            'description' => 'Directory packages',
        ]);

        $package = Package::firstOrCreate([
            'slug' => 'business-directory-standard-6m',
        ], [
            'package_type_id' => $packageType->id,
            'name' => 'Business Directory Standard',
            'description' => 'Standard package',
            'billing_model' => 'fixed',
            'is_self_service' => false,
            'duration_days' => 180,
            'status' => 'active',
        ]);

        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(10),
        ]);

        $listing->update([
            'active_subscription_id' => $subscription->id,
        ]);

        $foreignListing = Listing::create([
            'user_id' => $otherUser->id,
            'source_channel' => 'self_service',
            'title' => 'Foreign Listing',
            'slug' => 'foreign-listing',
            'status' => 'draft',
        ]);

        $indexResponse = $this->actingAs($owner)->get(route('account.listings.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('My Listings');
        $indexResponse->assertSee('Owner Workspace Listing');
        $indexResponse->assertDontSee('Foreign Listing');

        $showResponse = $this->actingAs($owner)->get(route('account.listings.show', $listing));
        $showResponse->assertOk();
        $showResponse->assertSee('Owner Workspace Listing');
        $showResponse->assertSee('Renew subscription');
        $showResponse->assertSee('Business Directory Standard');

        $forbiddenResponse = $this->actingAs($owner)->get(route('account.listings.show', $foreignListing));
        $forbiddenResponse->assertForbidden();
    }

    public function test_owner_can_edit_safe_listing_profile_fields_only_for_owned_listing(): void
    {
        $owner = User::factory()->create([
            'role' => 'business_owner',
        ]);
        $otherUser = User::factory()->create();

        $category = Category::create([
            'type' => 'listing',
            'name' => 'Services',
            'slug' => 'services',
        ]);

        $listing = Listing::create([
            'user_id' => $owner->id,
            'source_channel' => 'self_service',
            'title' => 'Original Listing',
            'slug' => 'original-listing',
            'status' => 'published',
            'city' => 'Bethlehem',
            'website_url' => 'https://old.example.com',
        ]);

        $foreignListing = Listing::create([
            'user_id' => $otherUser->id,
            'source_channel' => 'self_service',
            'title' => 'Foreign Editable Listing',
            'slug' => 'foreign-editable-listing',
            'status' => 'draft',
        ]);

        $editResponse = $this->actingAs($owner)->get(route('account.listings.edit', $listing));
        $editResponse->assertOk();
        $editResponse->assertSee('Edit Listing Profile');

        $updateResponse = $this->actingAs($owner)->put(route('account.listings.update', $listing), [
            'title' => 'Updated Listing Title',
            'excerpt' => 'Updated listing excerpt.',
            'description' => 'Updated listing description.',
            'website_url' => 'https://new.example.com',
            'email' => 'owner@example.com',
            'phone' => '0123456789',
            'address_line' => '123 Main Road',
            'city' => 'Clarens',
            'region' => 'Free State',
            'country' => 'South Africa',
            'postal_code' => '9707',
            'category_ids' => [$category->id],
        ]);

        $updateResponse->assertRedirect(route('account.listings.edit', $listing));

        $listing->refresh();
        $this->assertSame('Updated Listing Title', $listing->title);
        $this->assertSame('Clarens', $listing->city);
        $this->assertSame('https://new.example.com', $listing->website_url);
        $this->assertSame('published', $listing->status);
        $this->assertSame('original-listing', $listing->slug);
        $this->assertEquals([$category->id], $listing->categories()->pluck('categories.id')->all());

        $forbiddenEdit = $this->actingAs($owner)->get(route('account.listings.edit', $foreignListing));
        $forbiddenEdit->assertForbidden();

        $forbiddenUpdate = $this->actingAs($owner)->put(route('account.listings.update', $foreignListing), [
            'title' => 'Nope',
        ]);
        $forbiddenUpdate->assertForbidden();
    }

    public function test_owner_can_respond_to_approved_reviews_on_owned_listing(): void
    {
        $owner = User::factory()->create([
            'role' => 'business_owner',
        ]);
        $reviewAuthor = User::factory()->create();
        $otherOwner = User::factory()->create([
            'role' => 'business_owner',
        ]);

        $listing = Listing::create([
            'user_id' => $owner->id,
            'source_channel' => 'self_service',
            'title' => 'Review Response Listing',
            'slug' => 'review-response-listing',
            'status' => 'published',
        ]);

        $packageType = PackageType::firstOrCreate([
            'slug' => 'business_directory',
        ], [
            'name' => 'Business Directory',
            'description' => 'Directory packages',
        ]);

        $package = Package::firstOrCreate([
            'slug' => 'business-directory-standard-6m',
        ], [
            'package_type_id' => $packageType->id,
            'name' => 'Business Directory Standard',
            'description' => 'Standard package',
            'billing_model' => 'fixed',
            'is_self_service' => false,
            'duration_days' => 180,
            'status' => 'active',
        ]);

        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $listing->update([
            'active_subscription_id' => $subscription->id,
        ]);

        $review = Review::create([
            'listing_id' => $listing->id,
            'user_id' => $reviewAuthor->id,
            'rating' => 5,
            'title' => 'Excellent service',
            'body' => 'Very helpful team and quick turnaround.',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($owner)->post(route('account.listings.reviews.respond', [$listing, $review]), [
            'owner_response' => 'Thanks for the kind feedback. We appreciate your support.',
        ]);

        $response->assertRedirect(route('account.listings.show', $listing));

        $review->refresh();
        $this->assertSame('Thanks for the kind feedback. We appreciate your support.', $review->owner_response);
        $this->assertSame($owner->id, $review->responded_by_user_id);
        $this->assertNotNull($review->owner_responded_at);

        $workspaceResponse = $this->actingAs($owner)->get(route('account.listings.show', $listing));
        $workspaceResponse->assertOk();
        $workspaceResponse->assertSee('Your response');
        $workspaceResponse->assertSee('Thanks for the kind feedback. We appreciate your support.');

        $publicResponse = $this->get(route('directory.show', $listing));
        $publicResponse->assertOk();
        $publicResponse->assertSee('Business response');
        $publicResponse->assertSee('Thanks for the kind feedback. We appreciate your support.');

        $forbiddenResponse = $this->actingAs($otherOwner)->post(route('account.listings.reviews.respond', [$listing, $review]), [
            'owner_response' => 'Unauthorized response',
        ]);
        $forbiddenResponse->assertForbidden();
    }

    public function test_owner_can_manage_listing_gallery_photos(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create([
            'role' => 'business_owner',
        ]);

        $listing = Listing::create([
            'user_id' => $owner->id,
            'source_channel' => 'self_service',
            'title' => 'Gallery Listing',
            'slug' => 'gallery-listing',
            'status' => 'published',
        ]);

        $packageType = PackageType::firstOrCreate([
            'slug' => 'business_directory',
        ], [
            'name' => 'Business Directory',
            'description' => 'Directory packages',
        ]);

        $package = Package::firstOrCreate([
            'slug' => 'business-directory-standard-6m',
        ], [
            'package_type_id' => $packageType->id,
            'name' => 'Business Directory Standard',
            'description' => 'Standard package',
            'billing_model' => 'fixed',
            'is_self_service' => false,
            'duration_days' => 180,
            'status' => 'active',
        ]);

        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $listing->update([
            'active_subscription_id' => $subscription->id,
        ]);

        $uploadResponse = $this->actingAs($owner)->post(route('account.listings.photos.store', $listing), [
            'photo_upload' => UploadedFile::fake()->image('shopfront.jpg'),
            'caption' => 'Our updated shopfront',
        ]);

        $uploadResponse->assertRedirect(route('account.listings.show', $listing));

        $secondUploadResponse = $this->actingAs($owner)->post(route('account.listings.photos.store', $listing), [
            'photo_upload' => UploadedFile::fake()->image('interior.jpg'),
            'caption' => 'Our interior space',
        ]);

        $secondUploadResponse->assertRedirect(route('account.listings.show', $listing));

        $listing->refresh()->load('photos');
        $photo = $listing->photos->firstWhere('caption', 'Our updated shopfront');
        $secondPhoto = $listing->photos->firstWhere('caption', 'Our interior space');

        $this->assertNotNull($photo);
        $this->assertNotNull($secondPhoto);
        $this->assertSame('Our updated shopfront', $photo->caption);
        Storage::disk('public')->assertExists($photo->image_path);
        Storage::disk('public')->assertExists($secondPhoto->image_path);

        $makePrimaryResponse = $this->actingAs($owner)->post(route('account.listings.photos.primary', [$listing, $secondPhoto]));
        $makePrimaryResponse->assertRedirect(route('account.listings.show', $listing));

        $listing->refresh()->load('photos');
        $primaryPhoto = $listing->photos->first();

        $this->assertSame($secondPhoto->id, $primaryPhoto->id);

        $workspaceResponse = $this->actingAs($owner)->get(route('account.listings.show', $listing));
        $workspaceResponse->assertOk();
        $workspaceResponse->assertSee('Photo Gallery');
        $workspaceResponse->assertSee('Primary photo');
        $workspaceResponse->assertSee('Our updated shopfront');
        $workspaceResponse->assertSee('Our interior space');

        $publicResponse = $this->get(route('directory.show', $listing));
        $publicResponse->assertOk();
        $publicResponse->assertSee('Photo Gallery');
        $publicResponse->assertSee('Our updated shopfront');
        $publicResponse->assertSee('Our interior space');
        $publicResponse->assertSee(\Illuminate\Support\Facades\Storage::url($secondPhoto->image_path), false);

        $deleteResponse = $this->actingAs($owner)->delete(route('account.listings.photos.destroy', [$listing, $photo]));
        $deleteResponse->assertRedirect(route('account.listings.show', $listing));

        $this->assertDatabaseMissing('listing_photos', [
            'id' => $photo->id,
        ]);
        Storage::disk('public')->assertMissing($photo->image_path);

        $deleteListingResponse = $this->actingAs($owner)->delete(route('account.listings.destroy', $listing));
        $deleteListingResponse->assertRedirect(route('account.listings.index'));

        Storage::disk('public')->assertMissing($secondPhoto->image_path);
    }

    public function test_owner_can_manage_events_from_owned_listing_and_publishing_requires_active_listing(): void
    {
        $owner = User::factory()->create([
            'role' => 'business_owner',
        ]);
        $otherOwner = User::factory()->create([
            'role' => 'business_owner',
        ]);

        $activeListing = Listing::create([
            'user_id' => $owner->id,
            'source_channel' => 'self_service',
            'title' => 'Events Listing',
            'slug' => 'events-listing',
            'status' => 'published',
            'city' => 'Bethlehem',
        ]);

        $inactiveListing = Listing::create([
            'user_id' => $owner->id,
            'source_channel' => 'self_service',
            'title' => 'Inactive Events Listing',
            'slug' => 'inactive-events-listing',
            'status' => 'published',
        ]);

        $foreignListing = Listing::create([
            'user_id' => $otherOwner->id,
            'source_channel' => 'self_service',
            'title' => 'Foreign Events Listing',
            'slug' => 'foreign-events-listing',
            'status' => 'published',
        ]);

        $packageType = PackageType::firstOrCreate([
            'slug' => 'business_directory',
        ], [
            'name' => 'Business Directory',
            'description' => 'Directory packages',
        ]);

        $package = Package::firstOrCreate([
            'slug' => 'business-directory-standard-6m',
        ], [
            'package_type_id' => $packageType->id,
            'name' => 'Business Directory Standard',
            'description' => 'Standard package',
            'billing_model' => 'fixed',
            'is_self_service' => false,
            'duration_days' => 180,
            'status' => 'active',
        ]);

        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $activeListing->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $activeListing->update([
            'active_subscription_id' => $subscription->id,
        ]);

        $createResponse = $this->actingAs($owner)->post(route('account.listings.events.store', $activeListing), [
            'title' => 'Owner Event',
            'excerpt' => 'Owner-managed event.',
            'description' => 'Detailed owner event description.',
            'venue_name' => 'Town Hall',
            'city' => 'Bethlehem',
            'country' => 'South Africa',
            'start_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'end_at' => now()->addWeek()->addHours(2)->format('Y-m-d H:i:s'),
            'status' => 'published',
        ]);

        $event = Event::firstOrFail();
        $createResponse->assertRedirect(route('account.listings.events.edit', [$activeListing, $event]));

        $this->assertDatabaseHas('events', [
            'listing_id' => $activeListing->id,
            'user_id' => $owner->id,
            'title' => 'Owner Event',
            'status' => 'published',
        ]);

        $eventPackageType = PackageType::firstOrCreate([
            'slug' => 'events',
        ], [
            'name' => 'Events',
            'description' => 'Event promotion packages',
        ]);

        $eventPackage = Package::firstOrCreate([
            'slug' => 'event-standard-30d',
        ], [
            'package_type_id' => $eventPackageType->id,
            'name' => 'Event Standard',
            'description' => 'Standard event package',
            'billing_model' => 'fixed',
            'is_self_service' => false,
            'duration_days' => 30,
            'status' => 'active',
        ]);

        $eventSubscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $eventPackage->id,
            'subscribable_type' => Event::class,
            'subscribable_id' => $event->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(14),
        ]);

        $event->update([
            'active_subscription_id' => $eventSubscription->id,
        ]);

        $eventOrder = Order::create([
            'user_id' => $owner->id,
            'order_number' => 'ORD-EVT-1',
            'status' => 'pending',
            'currency' => 'ZAR',
            'subtotal' => 750,
            'vat_amount' => 0,
            'total' => 750,
            'placed_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $eventOrder->id,
            'package_id' => $eventPackage->id,
            'purchasable_type' => Event::class,
            'purchasable_id' => $event->id,
            'name_snapshot' => 'Owner Event Package',
            'unit_price' => 750,
            'quantity' => 1,
            'billing_model' => 'fixed',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        Invoice::create([
            'order_id' => $eventOrder->id,
            'invoice_number' => 'INV-EVT-1',
            'invoice_prefix_snapshot' => 'LIFE',
            'status' => 'issued',
            'currency' => 'ZAR',
            'subtotal' => 750,
            'vat_amount' => 0,
            'total' => 750,
            'issued_at' => now(),
        ]);

        Payment::create([
            'order_id' => $eventOrder->id,
            'user_id' => $owner->id,
            'provider' => 'payfast',
            'status' => 'pending',
            'amount' => 750,
            'currency' => 'ZAR',
        ]);

        $indexResponse = $this->actingAs($owner)->get(route('account.listings.events.index', $activeListing));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Owner Event');
        $indexResponse->assertSee('Buy event package');
        $indexResponse->assertSee('Event Standard');
        $indexResponse->assertSee('Renew event package');
        $indexResponse->assertSee('INV-EVT-1');

        $workspaceResponse = $this->actingAs($owner)->get(route('account.listings.show', $activeListing));
        $workspaceResponse->assertOk();
        $workspaceResponse->assertSee('Events');
        $workspaceResponse->assertSee('Owner Event');

        $editResponse = $this->actingAs($owner)->get(route('account.listings.events.edit', [$activeListing, $event]));
        $editResponse->assertOk();
        $editResponse->assertSee('Event Commerce Status');
        $editResponse->assertSee('Renew event package');
        $editResponse->assertSee('INV-EVT-1');

        $updateResponse = $this->actingAs($owner)->put(route('account.listings.events.update', [$activeListing, $event]), [
            'title' => 'Updated Owner Event',
            'excerpt' => 'Updated excerpt.',
            'description' => 'Updated details.',
            'venue_name' => 'Town Hall',
            'city' => 'Bethlehem',
            'country' => 'South Africa',
            'start_at' => now()->addWeeks(2)->format('Y-m-d H:i:s'),
            'end_at' => now()->addWeeks(2)->addHours(3)->format('Y-m-d H:i:s'),
            'status' => 'draft',
        ]);

        $updateResponse->assertRedirect(route('account.listings.events.edit', [$activeListing, $event]));
        $event->refresh();
        $this->assertSame('Updated Owner Event', $event->title);
        $this->assertSame('draft', $event->status);

        $failedPublishResponse = $this->from(route('account.listings.events.create', $inactiveListing))
            ->actingAs($owner)
            ->post(route('account.listings.events.store', $inactiveListing), [
                'title' => 'Blocked Published Event',
                'city' => 'Bethlehem',
                'country' => 'South Africa',
                'start_at' => now()->addWeek()->format('Y-m-d H:i:s'),
                'status' => 'published',
            ]);

        $failedPublishResponse->assertRedirect(route('account.listings.events.create', $inactiveListing));
        $failedPublishResponse->assertSessionHasErrors('status');

        $foreignEditResponse = $this->actingAs($owner)->get(route('account.listings.events.edit', [$foreignListing, $event]));
        $foreignEditResponse->assertForbidden();
    }

    public function test_owner_can_manage_advert_campaigns_from_owned_listing(): void
    {
        $owner = User::factory()->create([
            'role' => 'business_owner',
        ]);
        $otherOwner = User::factory()->create([
            'role' => 'business_owner',
        ]);

        $listing = Listing::create([
            'user_id' => $owner->id,
            'source_channel' => 'self_service',
            'title' => 'Advert Listing',
            'slug' => 'advert-listing',
            'status' => 'published',
            'website_url' => 'https://business.example.com',
        ]);

        $foreignListing = Listing::create([
            'user_id' => $otherOwner->id,
            'source_channel' => 'self_service',
            'title' => 'Foreign Advert Listing',
            'slug' => 'foreign-advert-listing',
            'status' => 'published',
        ]);

        $packageType = PackageType::firstOrCreate([
            'slug' => 'business_directory',
        ], [
            'name' => 'Business Directory',
            'description' => 'Directory packages',
        ]);

        $package = Package::firstOrCreate([
            'slug' => 'business-directory-standard-6m',
        ], [
            'package_type_id' => $packageType->id,
            'name' => 'Business Directory Standard',
            'description' => 'Standard package',
            'billing_model' => 'fixed',
            'is_self_service' => false,
            'duration_days' => 180,
            'status' => 'active',
        ]);

        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $listing->update([
            'active_subscription_id' => $subscription->id,
        ]);

        $storeResponse = $this->actingAs($owner)->post(route('account.listings.ad-campaigns.store', $listing), [
            'title' => 'Owner Advert Campaign',
            'headline' => 'Promote our business',
            'body' => 'Campaign body copy.',
            'destination_url' => 'https://business.example.com/offer',
            'status' => 'ready',
        ]);

        $campaign = AdCampaign::firstOrFail();
        $storeResponse->assertRedirect(route('account.listings.ad-campaigns.edit', [$listing, $campaign]));

        $this->assertDatabaseHas('ad_campaigns', [
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Owner Advert Campaign',
            'status' => 'ready',
        ]);

        $indexResponse = $this->actingAs($owner)->get(route('account.listings.ad-campaigns.index', $listing));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Owner Advert Campaign');
        $indexResponse->assertSee('Buy advert package');

        $editResponse = $this->actingAs($owner)->get(route('account.listings.ad-campaigns.edit', [$listing, $campaign]));
        $editResponse->assertOk();
        $editResponse->assertSee('Edit Advert Campaign');

        $updateResponse = $this->actingAs($owner)->put(route('account.listings.ad-campaigns.update', [$listing, $campaign]), [
            'title' => 'Updated Advert Campaign',
            'headline' => 'Updated headline',
            'body' => 'Updated body copy.',
            'destination_url' => 'https://business.example.com/new-offer',
            'status' => 'draft',
        ]);

        $updateResponse->assertRedirect(route('account.listings.ad-campaigns.edit', [$listing, $campaign]));
        $campaign->refresh();
        $this->assertSame('Updated Advert Campaign', $campaign->title);
        $this->assertSame('draft', $campaign->status);

        $checkoutResponse = $this->actingAs($owner)->get(route('checkout.index', [
            'campaign' => $campaign->slug,
        ]));
        $checkoutResponse->assertOk();
        $checkoutResponse->assertSee('Selected advert campaign:');
        $checkoutResponse->assertSee('Updated Advert Campaign');

        $workspaceResponse = $this->actingAs($owner)->get(route('account.listings.show', $listing));
        $workspaceResponse->assertOk();
        $workspaceResponse->assertSee('Advert Campaigns');
        $workspaceResponse->assertSee('Updated Advert Campaign');

        $forbiddenResponse = $this->actingAs($owner)->get(route('account.listings.ad-campaigns.edit', [$foreignListing, $campaign]));
        $forbiddenResponse->assertForbidden();
    }

    public function test_owner_can_manage_push_campaigns_from_owned_listing(): void
    {
        $owner = User::factory()->create([
            'role' => 'business_owner',
        ]);
        $otherOwner = User::factory()->create([
            'role' => 'business_owner',
        ]);

        $listing = Listing::create([
            'user_id' => $owner->id,
            'source_channel' => 'self_service',
            'title' => 'Push Listing',
            'slug' => 'push-listing',
            'status' => 'published',
            'city' => 'Bethlehem',
            'region' => 'Free State',
        ]);

        $foreignListing = Listing::create([
            'user_id' => $otherOwner->id,
            'source_channel' => 'self_service',
            'title' => 'Foreign Push Listing',
            'slug' => 'foreign-push-listing',
            'status' => 'published',
        ]);

        $packageType = PackageType::firstOrCreate([
            'slug' => 'business_directory',
        ], [
            'name' => 'Business Directory',
            'description' => 'Directory packages',
        ]);

        $package = Package::firstOrCreate([
            'slug' => 'business-directory-standard-6m',
        ], [
            'package_type_id' => $packageType->id,
            'name' => 'Business Directory Standard',
            'description' => 'Standard package',
            'billing_model' => 'fixed',
            'is_self_service' => false,
            'duration_days' => 180,
            'status' => 'active',
        ]);

        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $listing->update([
            'active_subscription_id' => $subscription->id,
        ]);

        $storeResponse = $this->actingAs($owner)->post(route('account.listings.push-campaigns.store', $listing), [
            'title' => 'Owner Push Campaign',
            'headline' => 'Limited special',
            'message' => 'Join us this weekend for a limited special.',
            'audience_scope' => 'listing_city',
            'target_city' => 'Bethlehem',
            'target_region' => 'Free State',
            'status' => 'ready',
        ]);

        $campaign = PushCampaign::firstOrFail();
        $storeResponse->assertRedirect(route('account.listings.push-campaigns.edit', [$listing, $campaign]));

        $this->assertDatabaseHas('push_campaigns', [
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Owner Push Campaign',
            'status' => 'ready',
        ]);

        $indexResponse = $this->actingAs($owner)->get(route('account.listings.push-campaigns.index', $listing));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Owner Push Campaign');
        $indexResponse->assertSee('Buy push package');

        $editResponse = $this->actingAs($owner)->get(route('account.listings.push-campaigns.edit', [$listing, $campaign]));
        $editResponse->assertOk();
        $editResponse->assertSee('Edit Push Campaign');

        $updateResponse = $this->actingAs($owner)->put(route('account.listings.push-campaigns.update', [$listing, $campaign]), [
            'title' => 'Updated Push Campaign',
            'headline' => 'Updated headline',
            'message' => 'Updated push copy.',
            'audience_scope' => 'custom_radius',
            'target_city' => 'Clarens',
            'target_region' => 'Free State',
            'radius_km' => 25,
            'status' => 'draft',
        ]);

        $updateResponse->assertRedirect(route('account.listings.push-campaigns.edit', [$listing, $campaign]));
        $campaign->refresh();
        $this->assertSame('Updated Push Campaign', $campaign->title);
        $this->assertSame('draft', $campaign->status);

        $pushPackage = Package::where('slug', 'push-campaign-once')->firstOrFail();
        $pushSubscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $pushPackage->id,
            'subscribable_type' => PushCampaign::class,
            'subscribable_id' => $campaign->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $campaign->update([
            'status' => 'active',
            'active_subscription_id' => $pushSubscription->id,
        ]);

        $dispatchResponse = $this->actingAs($owner)
            ->followingRedirects()
            ->post(route('account.listings.push-campaigns.dispatch', [$listing, $campaign]));
        $dispatchResponse->assertOk();
        $dispatchResponse->assertSee('Push campaign dispatched.');

        $campaign->refresh();
        $this->assertNotNull($campaign->sent_at);
        $this->assertDatabaseHas('notification_logs', [
            'channel' => 'push',
            'notification_type' => 'push_campaign_sent',
            'notifiable_type' => PushCampaign::class,
            'notifiable_id' => $campaign->id,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'push_campaign.dispatched',
            'subject_type' => PushCampaign::class,
            'subject_id' => $campaign->id,
        ]);

        $checkoutResponse = $this->actingAs($owner)->get(route('checkout.index', [
            'push_campaign' => $campaign->slug,
        ]));
        $checkoutResponse->assertOk();
        $checkoutResponse->assertSee('Selected push campaign:');
        $checkoutResponse->assertSee('Updated Push Campaign');

        $workspaceResponse = $this->actingAs($owner)->get(route('account.listings.show', $listing));
        $workspaceResponse->assertOk();
        $workspaceResponse->assertSee('Push Campaigns');
        $workspaceResponse->assertSee('Updated Push Campaign');

        $dispatchedEditResponse = $this->actingAs($owner)->get(route('account.listings.push-campaigns.edit', [$listing, $campaign]));
        $dispatchedEditResponse->assertOk();
        $dispatchedEditResponse->assertSee('Delivery History');
        $dispatchedEditResponse->assertSee('Custom radius');

        $forbiddenResponse = $this->actingAs($owner)->get(route('account.listings.push-campaigns.edit', [$foreignListing, $campaign]));
        $forbiddenResponse->assertForbidden();
    }
}
