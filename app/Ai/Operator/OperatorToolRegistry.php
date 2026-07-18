<?php

namespace App\Ai\Operator;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Ai\Operator\Tools\AiOperationsSummaryTool;
use App\Ai\Operator\Tools\ApplyArticleStatusTool;
use App\Ai\Operator\Tools\CampaignSummaryTool;
use App\Ai\Operator\Tools\ContentReviewQueueTool;
use App\Ai\Operator\Tools\FinanceSummaryTool;
use App\Ai\Operator\Tools\ListingReviewQueueTool;
use App\Ai\Operator\Tools\PlatformHealthTool;
use App\Ai\Operator\Tools\ProposeArticleStatusTool;
use App\Ai\Operator\Tools\RecentAuditTool;
use App\Ai\Operator\Tools\ResearchSummaryTool;
use App\Ai\Operator\Tools\UserSummaryTool;

class OperatorToolRegistry
{
    public function __construct(
        private readonly PlatformHealthTool $health,
        private readonly ContentReviewQueueTool $contentReviewQueue,
        private readonly ResearchSummaryTool $researchSummary,
        private readonly UserSummaryTool $userSummary,
        private readonly ListingReviewQueueTool $listingReviewQueue,
        private readonly CampaignSummaryTool $campaignSummary,
        private readonly FinanceSummaryTool $financeSummary,
        private readonly AiOperationsSummaryTool $aiOperationsSummary,
        private readonly RecentAuditTool $recentAudit,
        private readonly ProposeArticleStatusTool $articleStatus,
        private readonly ApplyArticleStatusTool $applyArticleStatus,
    ) {}

    /** @return list<OperatorTool> */
    public function all(): array
    {
        return [
            $this->health,
            $this->contentReviewQueue,
            $this->researchSummary,
            $this->userSummary,
            $this->listingReviewQueue,
            $this->campaignSummary,
            $this->financeSummary,
            $this->aiOperationsSummary,
            $this->recentAudit,
            $this->articleStatus,
            $this->applyArticleStatus,
        ];
    }

    public function get(string $name): OperatorTool
    {
        $tool = collect($this->all())->first(fn (OperatorTool $tool): bool => $tool->name() === $name);

        return $tool ?: throw new \InvalidArgumentException('Operator tool is not registered: '.$name);
    }
}
