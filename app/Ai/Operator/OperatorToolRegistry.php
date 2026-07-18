<?php

namespace App\Ai\Operator;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Ai\Operator\Tools\AiOperationsSummaryTool;
use App\Ai\Operator\Tools\ApplyArticleStatusTool;
use App\Ai\Operator\Tools\BuildDossierTool;
use App\Ai\Operator\Tools\CampaignSummaryTool;
use App\Ai\Operator\Tools\CompareSourcesTool;
use App\Ai\Operator\Tools\ContentReviewQueueTool;
use App\Ai\Operator\Tools\ContentSearchTool;
use App\Ai\Operator\Tools\CreateDirectoryListingTool;
use App\Ai\Operator\Tools\CreateEventArticleTool;
use App\Ai\Operator\Tools\FinanceSummaryTool;
use App\Ai\Operator\Tools\FindDirectoryDuplicatesTool;
use App\Ai\Operator\Tools\ListingReviewQueueTool;
use App\Ai\Operator\Tools\PlatformHealthTool;
use App\Ai\Operator\Tools\ProposeArticleStatusTool;
use App\Ai\Operator\Tools\RecentAuditTool;
use App\Ai\Operator\Tools\ResearchSummaryTool;
use App\Ai\Operator\Tools\SnapshotSourceTool;
use App\Ai\Operator\Tools\UpdateArticleTool;
use App\Ai\Operator\Tools\UpdateDirectoryListingTool;
use App\Ai\Operator\Tools\UserSummaryTool;
use App\Ai\Operator\Tools\WebSearchTool;

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
        private readonly WebSearchTool $webSearch,
        private readonly SnapshotSourceTool $snapshotSource,
        private readonly CreateDirectoryListingTool $createDirectoryListing,
        private readonly CreateEventArticleTool $createEventArticle,
        private readonly ContentSearchTool $contentSearch,
        private readonly FindDirectoryDuplicatesTool $findDirectoryDuplicates,
        private readonly CompareSourcesTool $compareSources,
        private readonly BuildDossierTool $buildDossier,
        private readonly UpdateDirectoryListingTool $updateDirectoryListing,
        private readonly UpdateArticleTool $updateArticle,
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
            $this->webSearch,
            $this->snapshotSource,
            $this->createDirectoryListing,
            $this->createEventArticle,
            $this->contentSearch,
            $this->findDirectoryDuplicates,
            $this->compareSources,
            $this->buildDossier,
            $this->updateDirectoryListing,
            $this->updateArticle,
        ];
    }

    public function get(string $name): OperatorTool
    {
        $tool = collect($this->all())->first(fn (OperatorTool $tool): bool => $tool->name() === $name);

        return $tool ?: throw new \InvalidArgumentException('Operator tool is not registered: '.$name);
    }
}
