<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_read_tool_is_authorized_and_audited(): void
    {
        config()->set('ai_platform.operator.enabled', true);
        $dev = User::factory()->create(['role' => 'dev']);

        $this->actingAs($dev)->postJson(route('admin.ai-operator.tools.execute', 'platform.health'), [
            'arguments' => [],
            'idempotency_key' => 'health-check-1',
        ])->assertOk()->assertJsonPath('ok', true)->assertJsonPath('risk', 'R0');

        $this->assertDatabaseHas('operator_tool_runs', ['tool' => 'platform.health', 'status' => 'succeeded']);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $dev->id, 'action' => 'ai_operator.tool.platform.health']);
        $this->assertNotEmpty(AuditLog::query()->latest('id')->first()->after_json['run_id']);
    }

    public function test_content_change_tool_creates_proposal_without_mutating_article(): void
    {
        config()->set('ai_platform.operator.enabled', true);
        $editor = User::factory()->create(['role' => 'editor']);
        $article = Article::create(['title' => 'Review me', 'slug' => 'review-me', 'status' => 'draft']);

        $this->actingAs($editor)->postJson(route('admin.ai-operator.tools.execute', 'content.propose_article_status'), [
            'arguments' => ['article_id' => $article->id, 'status' => 'published', 'reason' => 'Ready for human approval.'],
            'idempotency_key' => 'proposal-'.$article->id,
        ])->assertOk()->assertJsonPath('risk', 'R1')->assertJsonPath('result.proposed', true);

        $this->assertSame('draft', $article->fresh()->status);
        $this->assertDatabaseHas('ai_manager_actions', ['source_type' => Article::class, 'source_id' => $article->id, 'status' => 'proposed']);
    }

    public function test_risk_two_mutation_requires_matching_fresh_signed_approval(): void
    {
        config([
            'ai_platform.operator.enabled' => true,
            'ai_platform.operator.mutations_enabled' => true,
        ]);
        $editor = User::factory()->create(['role' => 'editor']);
        $article = Article::create(['title' => 'Approved publish', 'slug' => 'approved-publish', 'status' => 'draft']);
        $arguments = ['article_id' => $article->id, 'status' => 'published', 'reason' => 'Human reviewed this draft.'];

        $approval = $this->actingAs($editor)->postJson(route('admin.ai-operator.tools.approve', 'content.apply_article_status'), [
            'arguments' => $arguments,
        ])->assertOk()->json('approval_token');

        $this->postJson(route('admin.ai-operator.tools.execute', 'content.apply_article_status'), [
            'arguments' => $arguments,
            'idempotency_key' => 'publish-'.$article->id,
            'approval_token' => $approval,
        ])->assertOk()->assertJsonPath('result.verified', true);

        $this->assertSame('published', $article->fresh()->status);
        $this->assertDatabaseHas('operator_tool_approvals', ['approved_by' => $editor->id]);
        $this->assertDatabaseHas('article_word_ledgers', [
            'article_id' => $article->id,
            'approved_by_user_id' => $editor->id,
        ]);
    }

    public function test_failed_mutation_keeps_a_failed_run_and_audit_record(): void
    {
        config([
            'ai_platform.operator.enabled' => true,
            'ai_platform.operator.mutations_enabled' => true,
        ]);
        $editor = User::factory()->create(['role' => 'editor']);
        $article = Article::create(['title' => 'Stale approval', 'slug' => 'stale-approval', 'status' => 'draft']);
        $arguments = ['article_id' => $article->id, 'status' => 'published', 'reason' => 'Review complete.'];
        $approval = $this->actingAs($editor)->postJson(route('admin.ai-operator.tools.approve', 'content.apply_article_status'), [
            'arguments' => $arguments,
        ])->assertOk()->json('approval_token');

        $article->update(['title' => 'Changed after approval']);

        $this->postJson(route('admin.ai-operator.tools.execute', 'content.apply_article_status'), [
            'arguments' => $arguments,
            'idempotency_key' => 'stale-'.$article->id,
            'approval_token' => $approval,
        ])->assertForbidden();

        $this->assertDatabaseHas('operator_tool_runs', ['tool' => 'content.apply_article_status', 'status' => 'failed']);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $editor->id, 'action' => 'ai_operator.tool.content.apply_article_status']);
    }
}
