<?php

namespace App\Console\Commands;

use App\Services\AiGatewayService;
use Illuminate\Console\Command;

class ValidateAiConfigurationCommand extends Command
{
    protected $signature = 'life:ai:validate-config';
    protected $description = 'Reject AI feature routes whose providers lack required capabilities.';

    public function handle(AiGatewayService $gateway): int
    {
        $errors = [];
        try {
            $routes = $gateway->featureRoutes();
        } catch (\Illuminate\Database\QueryException) {
            $this->warn('The settings database is unavailable; validating environment and config defaults only.');
            $defaultProvider = (string) config('services.ai.provider', 'openrouter');
            $defaultFallbacks = (array) config('services.ai.fallback_providers', []);
            $routes = collect((array) config('ai_features.routes', []))->map(function (array $route, string $key) use ($defaultProvider, $defaultFallbacks): array {
                return [
                    'key' => $key,
                    'provider' => (string) ($route['provider'] ?? $defaultProvider),
                    'fallback_providers' => (array) ($route['fallback_providers'] ?? $defaultFallbacks),
                ];
            })->values()->all();
        }

        foreach ($routes as $route) {
            foreach (array_unique([$route['provider'], ...$route['fallback_providers']]) as $provider) {
                try {
                    $gateway->assertFeatureCapabilities($route['key'], $provider);
                } catch (\RuntimeException $exception) {
                    $errors[] = $exception->getMessage();
                }
            }
        }
        foreach ($errors as $error) {
            $this->error($error);
        }
        if ($errors === []) {
            $this->info('AI provider capability configuration is valid.');
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }
}
