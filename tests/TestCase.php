<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config()->set('ai_platform.knowledge.auto_index', false);
        config()->set('localization.auto_translate_on_publish', false);
    }
}
