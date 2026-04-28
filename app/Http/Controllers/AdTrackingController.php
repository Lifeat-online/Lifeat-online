<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\PushCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class AdTrackingController extends Controller
{
    /** 1×1 transparent GIF — returned for every tracking pixel request. */
    private const PIXEL_GIF = "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00\x21\xf9\x04\x00\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3b";

    /**
     * Impression pixel — increment impression counter and return a 1×1 GIF.
     * Called by the <img> tag embedded in every rendered ad card.
     */
    public function impression(AdCampaign $adCampaign): Response
    {
        if ($adCampaign->status === 'active') {
            $adCampaign->increment('impressions');
        }

        return response(self::PIXEL_GIF, 200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /**
     * Click redirect — increment click counter and forward to the destination URL.
     * All ad CTA links point here instead of directly to the destination.
     */
    public function click(AdCampaign $adCampaign): RedirectResponse
    {
        if ($adCampaign->status === 'active') {
            $adCampaign->increment('clicks');
        }

        $destination = $adCampaign->destination_url
            ?: route('directory.show', $adCampaign->listing ?? $adCampaign->listing_id);

        return redirect()->away($destination);
    }

    /**
     * Push open pixel — increment open_count on the push campaign and return a 1×1 GIF.
     * Embedded as a tracking pixel in the push notification body or landing page.
     */
    public function pushOpen(PushCampaign $pushCampaign): Response
    {
        if ($pushCampaign->sent_at) {
            $pushCampaign->increment('open_count');
        }

        return response(self::PIXEL_GIF, 200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }
}
