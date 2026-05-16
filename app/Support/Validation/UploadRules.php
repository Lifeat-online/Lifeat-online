<?php

namespace App\Support\Validation;

class UploadRules
{
    /**
     * @return array<int, string>
     */
    public static function optionalPublicImage(int $maxKilobytes = 5120): array
    {
        return ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$maxKilobytes}"];
    }

    /**
     * @return array<int, string>
     */
    public static function requiredPublicImage(int $maxKilobytes = 5120): array
    {
        return ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$maxKilobytes}"];
    }

    /**
     * @return array<int, string>
     */
    public static function requiredPrivateDocument(int $maxKilobytes = 5120): array
    {
        return ['required', 'file', 'mimes:pdf,jpg,jpeg,png', "max:{$maxKilobytes}"];
    }
}
