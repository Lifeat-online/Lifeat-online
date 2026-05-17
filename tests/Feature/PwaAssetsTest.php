<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaAssetsTest extends TestCase
{
    public function test_public_layout_includes_install_metadata(): void
    {
        $response = $this->get(route('legal.terms'));

        $response->assertOk();
        $response->assertSee('rel="manifest"', false);
        $response->assertSee('/manifest.webmanifest', false);
        $response->assertSee('apple-mobile-web-app-capable', false);
        $response->assertSee('/pwa/apple-touch-icon.png', false);
    }

    public function test_manifest_is_installable(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Life Platform', $manifest['name']);
        $this->assertSame('/?source=pwa', $manifest['start_url']);
        $this->assertSame('fullscreen', $manifest['display']);
        $this->assertSame('fullscreen', $manifest['display_override'][0]);
        $this->assertContains('/pwa/icon-512-maskable.png', array_column($manifest['icons'], 'src'));
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
    }

    public function test_service_worker_and_offline_shell_are_available(): void
    {
        $serviceWorker = file_get_contents(public_path('sw.js'));
        $offlineShell = file_get_contents(public_path('offline.html'));

        $this->assertStringContainsString('life-pwa-', $serviceWorker);
        $this->assertStringContainsString('/offline.html', $serviceWorker);
        $this->assertStringContainsString('const responseForCache = response.clone();', $serviceWorker);
        $this->assertStringContainsString('await cache.put(request, responseForCache);', $serviceWorker);
        $this->assertStringContainsString('You are offline', $offlineShell);
    }
}
