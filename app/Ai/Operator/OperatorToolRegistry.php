<?php

namespace App\Ai\Operator;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Ai\Operator\Tools\PlatformHealthTool;
use App\Ai\Operator\Tools\ProposeArticleStatusTool;
use App\Ai\Operator\Tools\ApplyArticleStatusTool;

class OperatorToolRegistry
{
    public function __construct(private readonly PlatformHealthTool $health, private readonly ProposeArticleStatusTool $articleStatus, private readonly ApplyArticleStatusTool $applyArticleStatus) {}

    public function get(string $name): OperatorTool
    {
        $tool = collect([$this->health, $this->articleStatus, $this->applyArticleStatus])->first(fn (OperatorTool $tool): bool => $tool->name() === $name);

        return $tool ?: throw new \InvalidArgumentException('Operator tool is not registered: '.$name);
    }
}
