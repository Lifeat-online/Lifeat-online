<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\User;
use App\Support\Monitoring\HealthReport;

class PlatformHealthTool implements OperatorTool
{
    public function __construct(private readonly HealthReport $health) {}
    public function name(): string { return 'platform.health'; }
    public function risk(): string { return 'R0'; }
    public function rules(): array { return []; }
    public function authorize(User $user): bool { return $user->hasRole('admin', 'support', 'dev', 'developer'); }
    public function recordVersion(array $arguments): string { return 'read-only'; }
    public function execute(User $user, array $arguments): array { return $this->health->run(); }
}
