<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Setting;
use App\Models\WriterApplication;
use App\Services\AdvertisingBundleCheckoutService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdvertiseController extends Controller
{
    public function index(): View
    {
        $directoryPackages = Package::with(['type', 'prices'])
            ->active()
            ->whereHas('type', fn ($query) => $query->where('slug', 'business_directory'))
            ->orderBy('is_self_service')
            ->get();

        $eventPackages = Package::with(['type', 'prices'])
            ->active()
            ->whereHas('type', fn ($query) => $query->where('slug', 'event_package'))
            ->orderBy('name')
            ->get();

        $advertPackages = Package::with(['type', 'prices'])
            ->active()
            ->whereHas('type', fn ($query) => $query->where('slug', 'advert_package'))
            ->orderBy('name')
            ->get();

        $pushPackages = Package::with(['type', 'prices'])
            ->active()
            ->whereHas('type', fn ($query) => $query->where('slug', 'push_campaign'))
            ->orderBy('name')
            ->get();

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
            'pricing' => [
                'directory_standard_6m' => (float) Setting::getValue('pricing.business_directory_6m', 500),
                'directory_self_service_6m' => (float) Setting::getValue('pricing.business_directory_self_service_6m', 750),
                'event_one_off' => (float) Setting::getValue('pricing.event_one_off', 250),
                'event_monthly' => (float) Setting::getValue('pricing.event_monthly', 99),
                'push_notification' => (float) Setting::getValue('pricing.push_notification', 0),
            ],
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
