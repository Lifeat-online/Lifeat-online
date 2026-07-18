<?php

namespace App\Ai\Operator\Contracts;

use App\Models\User;

interface OperatorTool
{
    public function name(): string;
    public function risk(): string;
    public function rules(): array;
    public function authorize(User $user): bool;
    public function recordVersion(array $arguments): string;
    public function execute(User $user, array $arguments): array;
}
