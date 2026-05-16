<?php

namespace Tests\Unit;

use App\Support\Validation\UploadRules;
use PHPUnit\Framework\TestCase;

class UploadRulesTest extends TestCase
{
    public function test_public_image_rules_allow_only_expected_web_image_types(): void
    {
        $this->assertSame(
            ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            UploadRules::optionalPublicImage(2048)
        );

        $this->assertSame(
            ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            UploadRules::requiredPublicImage()
        );
    }

    public function test_private_document_rules_are_limited_to_pdf_and_static_images(): void
    {
        $this->assertSame(
            ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            UploadRules::requiredPrivateDocument()
        );
    }
}
