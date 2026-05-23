<?php

namespace App\Support\Uploads;

use App\Models\AdCampaign;
use App\Models\Article;
use App\Models\CivicFaultPhoto;
use App\Models\Classified;
use App\Models\Event;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\WriterApplication;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class UploadReferenceIndex
{
    /**
     * @return array<int, string>
     */
    public function orphaned(string $disk): array
    {
        $known = $this->referenced($disk);

        return collect($this->files($disk))
            ->reject(fn (string $path) => $known->contains($path))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function files(string $disk): array
    {
        $prefixes = $disk === 'local'
            ? $this->privatePrefixes()
            : $this->publicPrefixes();

        return collect($prefixes)
            ->flatMap(fn (string $prefix) => Storage::disk($disk)->allFiles($prefix))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    private function referenced(string $disk): Collection
    {
        if ($disk === 'local') {
            return collect()
                ->merge(WriterApplication::query()->pluck('id_document_path'))
                ->merge(WriterApplication::query()->pluck('banking_document_path'))
                ->merge(WriterApplication::query()->pluck('proof_of_residence_path'))
                ->filter()
                ->unique()
                ->values();
        }

        return collect()
            ->merge(Listing::query()->pluck('featured_image'))
            ->merge(Listing::query()->pluck('logo_path'))
            ->merge(ListingPhoto::query()->pluck('image_path'))
            ->merge(Event::query()->pluck('featured_image'))
            ->merge(Article::query()->pluck('featured_image'))
            ->merge(Classified::query()->pluck('featured_image'))
            ->merge(AdCampaign::query()->pluck('creative_image'))
            ->merge(CivicFaultPhoto::query()->pluck('path'))
            ->merge(WriterApplication::query()->pluck('profile_photo_path'))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function publicPrefixes(): array
    {
        return [
            'articles/featured',
            'articles/ai-generated',
            'campaigns/creative',
            'classifieds',
            'events/featured',
            'fault-reports',
            'listings/featured',
            'listings/gallery',
            'listings/logos',
            'writer-applications/profile-photos',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function privatePrefixes(): array
    {
        return [
            'writer-applications/banking-documents',
            'writer-applications/id-documents',
            'writer-applications/proof-of-residence',
        ];
    }
}
