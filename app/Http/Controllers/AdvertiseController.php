<?php

namespace App\Http\Controllers;

use App\Models\WriterApplication;
use App\Services\AdvertisingBundleCheckoutService;
use App\Support\Caching\PublicReadCache;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdvertiseController extends Controller
{
    public function index(): View
    {
        $directoryPackages = PublicReadCache::activePackagesForType('business_directory', 'self_service');
        $eventPackages = PublicReadCache::activePackagesForType('event_package');
        $advertPackages = PublicReadCache::activePackagesForType('advert_package');
        $pushPackages = PublicReadCache::activePackagesForType('push_campaign');

        $approvedStaff = WriterApplication::query()
            ->where('status', WriterApplication::STATUS_APPROVED)
            ->where('assigned_role', WriterApplication::ROLE_STAFF)
            ->whereNotNull('phone')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (WriterApplication $application) => [
                'name' => $application->fullName(),
                'bio' => $application->profile_bio,
                'phone' => $application->phone,
                'phone_href' => 'tel:'.preg_replace('/\s+/', '', $application->phone),
                'email' => $application->email,
                'available_on_whatsapp' => $application->available_on_whatsapp,
                'whatsapp_href' => $application->available_on_whatsapp
                    ? $this->whatsAppUrl($application->phone)
                    : null,
                'profile_photo_url' => $application->profile_photo_path
                    ? asset('storage/'.$application->profile_photo_path)
                    : null,
            ])
            ->values();

        return view('advertise.index', [
            'directoryPackages' => $directoryPackages,
            'eventPackages' => $eventPackages,
            'advertPackages' => $advertPackages,
            'pushPackages' => $pushPackages,
            'approvedStaff' => $approvedStaff,
        ]);
    }

    public function start(Request $request, AdvertisingBundleCheckoutService $bundleCheckout): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'listing_package_slug' => ['required', 'string', 'exists:packages,slug'],
            'event_package_slug' => ['nullable', 'string', 'exists:packages,slug'],
            'event_title' => ['nullable', 'string', 'max:255'],
            'advert_package_slugs' => ['nullable', 'array'],
            'advert_package_slugs.*' => ['string', 'exists:packages,slug'],
            'push_package_slug' => ['nullable', 'string', 'exists:packages,slug'],
            'voucher_enabled' => ['nullable', 'boolean'],
            'voucher_redemption_model' => ['nullable', Rule::in(['once_off', 'numbered_uses', 'date_window'])],
            'voucher_title' => ['required_if:voucher_enabled,1', 'nullable', 'string', 'max:255'],
            'voucher_description' => ['nullable', 'string', 'max:2000'],
            'voucher_usage_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'voucher_start_at' => ['nullable', 'date'],
            'voucher_end_at' => ['nullable', 'date', 'after_or_equal:voucher_start_at'],
            'voucher_terms' => ['nullable', 'string', 'max:6000'],
        ]);

        $order = $bundleCheckout->create($request->user(), $validated);

        return redirect()
            ->route('checkout.show', $order)
            ->with('status', 'Your advertising bundle is ready for checkout.');
    }

    private function whatsAppUrl(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (str_starts_with($digits, '0')) {
            $digits = '27'.substr($digits, 1);
        }

        return 'https://wa.me/'.$digits;
    }
}
