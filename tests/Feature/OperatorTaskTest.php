<?php

namespace Tests\Feature;

use App\Ai\Operator\Contracts\OperatorTaskPlanner;
use App\Ai\Operator\OperatorTaskOrchestrator;
use App\Jobs\RunOperatorTask;
use App\Models\Article;
use App\Models\OperatorConversation;
use App\Models\OperatorTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OperatorTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_submit_a_natural_language_operator_task(): void
    {
        config([
            'ai_platform.operator.enabled' => true,
            'ai_platform.operator.agent_enabled' => true,
        ]);
        Queue::fake();
        $dev = User::factory()->create(['role' => 'dev']);

        $response = $this->actingAs($dev)->postJson(route('admin.ai-operator.tasks.store'), [
            'message' => 'Research Acme Bakery and add it to the business directory.',
        ]);

        $response->assertAccepted()
            ->assertJsonPath('status', OperatorTask::STATUS_PLANNED)
            ->assertJsonStructure(['task_id', 'conversation_id', 'status_url']);

        $task = OperatorTask::query()->firstOrFail();
        $this->assertSame($dev->id, $task->user_id);
        $this->assertSame('Research Acme Bakery and add it to the business directory.', $task->goal);
        $this->assertDatabaseHas('operator_messages', [
            'operator_conversation_id' => $task->operator_conversation_id,
            'role' => 'user',
            'content' => $task->goal,
        ]);
        Queue::assertPushed(RunOperatorTask::class, fn (RunOperatorTask $job): bool => $job->taskId === $task->id);
    }

    public function test_enhanced_operator_tasks_are_developer_only(): void
    {
        config([
            'ai_platform.operator.enabled' => true,
            'ai_platform.operator.agent_enabled' => true,
        ]);
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->postJson(route('admin.ai-operator.tasks.store'), [
            'message' => 'Publish the latest article.',
        ])->assertForbidden();
    }

    public function test_developer_can_poll_and_cancel_only_their_own_task(): void
    {
        config([
            'ai_platform.operator.enabled' => true,
            'ai_platform.operator.agent_enabled' => true,
        ]);
        Queue::fake();
        $dev = User::factory()->create(['role' => 'dev']);
        $other = User::factory()->create(['role' => 'dev']);

        $taskId = $this->actingAs($dev)->postJson(route('admin.ai-operator.tasks.store'), [
            'message' => 'Check platform health.',
        ])->assertAccepted()->json('task_id');

        $this->actingAs($other)->getJson(route('admin.ai-operator.tasks.show', $taskId))->assertNotFound();
        $this->actingAs($dev)->getJson(route('admin.ai-operator.tasks.show', $taskId))
            ->assertOk()
            ->assertJsonPath('id', $taskId)
            ->assertJsonPath('status', OperatorTask::STATUS_PLANNED);

        $this->postJson(route('admin.ai-operator.tasks.cancel', $taskId))
            ->assertOk()
            ->assertJsonPath('status', OperatorTask::STATUS_CANCELLED);
        $this->assertSame(OperatorTask::STATUS_CANCELLED, OperatorTask::findOrFail($taskId)->status);
    }

    public function test_orchestrator_executes_authorized_steps_and_completes_the_task(): void
    {
        config([
            'ai_platform.operator.enabled' => true,
            'ai_platform.operator.agent_enabled' => true,
        ]);
        $dev = User::factory()->create(['role' => 'dev']);
        $task = $this->createTask($dev, 'Check platform health.');
        $this->app->instance(OperatorTaskPlanner::class, new SequenceTaskPlanner([
            ['action' => 'tool', 'plan' => ['Check platform health'], 'tool' => 'platform.health', 'arguments' => [], 'summary' => 'Checking platform health.'],
            ['action' => 'complete', 'summary' => 'Platform health check completed.'],
        ]));

        app(OperatorTaskOrchestrator::class)->run($task->id);

        $task->refresh();
        $this->assertSame(OperatorTask::STATUS_COMPLETED, $task->status);
        $this->assertSame('Platform health check completed.', $task->result['summary']);
        $this->assertDatabaseHas('operator_task_steps', ['operator_task_id' => $task->id, 'tool' => 'platform.health', 'status' => 'succeeded']);
        $this->assertDatabaseHas('operator_tool_runs', ['tool' => 'platform.health', 'status' => 'succeeded']);
        $this->assertDatabaseHas('operator_messages', ['operator_conversation_id' => $task->operator_conversation_id, 'role' => 'assistant']);
    }

    public function test_orchestrator_pauses_before_a_critical_tool_mutation(): void
    {
        config([
            'ai_platform.operator.enabled' => true,
            'ai_platform.operator.agent_enabled' => true,
            'ai_platform.operator.mutations_enabled' => true,
        ]);
        $dev = User::factory()->create(['role' => 'dev']);
        $article = Article::create(['title' => 'Needs approval', 'slug' => 'needs-approval', 'status' => 'draft']);
        $task = $this->createTask($dev, 'Publish the article.');
        $this->app->instance(OperatorTaskPlanner::class, new SequenceTaskPlanner([
            [
                'action' => 'tool',
                'plan' => ['Publish the selected article'],
                'tool' => 'content.apply_article_status',
                'arguments' => ['article_id' => $article->id, 'status' => 'published', 'reason' => 'Developer requested publication.'],
                'summary' => 'Approval is required before publishing.',
            ],
        ]));

        app(OperatorTaskOrchestrator::class)->run($task->id);

        $this->assertSame(OperatorTask::STATUS_WAITING_FOR_APPROVAL, $task->fresh()->status);
        $this->assertSame('draft', $article->fresh()->status);
        $this->assertDatabaseHas('operator_task_steps', [
            'operator_task_id' => $task->id,
            'tool' => 'content.apply_article_status',
            'risk' => 'R2',
            'status' => OperatorTask::STATUS_WAITING_FOR_APPROVAL,
        ]);
        $this->assertDatabaseMissing('operator_tool_runs', ['tool' => 'content.apply_article_status']);
    }

    public function test_developer_can_approve_the_exact_paused_step(): void
    {
        config([
            'ai_platform.operator.enabled' => true,
            'ai_platform.operator.agent_enabled' => true,
            'ai_platform.operator.mutations_enabled' => true,
        ]);
        $dev = User::factory()->create(['role' => 'dev']);
        $article = Article::create(['title' => 'Approved task article', 'slug' => 'approved-task-article', 'status' => 'draft']);
        $task = $this->createTask($dev, 'Publish the approved article.');
        $this->app->instance(OperatorTaskPlanner::class, new SequenceTaskPlanner([[
            'action' => 'tool',
            'tool' => 'content.apply_article_status',
            'arguments' => ['article_id' => $article->id, 'status' => 'published', 'reason' => 'Developer approved publication.'],
            'summary' => 'Approval required.',
        ]]));
        app(OperatorTaskOrchestrator::class)->run($task->id);
        Queue::fake();
        $step = $task->steps()->firstOrFail();

        $this->actingAs($dev)->postJson(route('admin.ai-operator.tasks.approve', $task), [
            'step_id' => $step->id,
        ])->assertOk()->assertJsonPath('status', OperatorTask::STATUS_PLANNED);

        $this->assertSame('published', $article->fresh()->status);
        $this->assertDatabaseHas('operator_task_steps', ['id' => $step->id, 'status' => 'succeeded']);
        $this->assertDatabaseHas('operator_tool_approvals', ['approved_by' => $dev->id, 'tool' => 'content.apply_article_status']);
        Queue::assertPushed(RunOperatorTask::class, fn (RunOperatorTask $job): bool => $job->taskId === $task->id);
    }

    public function test_developer_can_reply_to_or_reject_a_paused_task(): void
    {
        config([
            'ai_platform.operator.enabled' => true,
            'ai_platform.operator.agent_enabled' => true,
        ]);
        Queue::fake();
        $dev = User::factory()->create(['role' => 'dev']);
        $task = $this->createTask($dev, 'Research an ambiguous business.');
        $this->app->instance(OperatorTaskPlanner::class, new SequenceTaskPlanner([[
            'action' => 'ask_user',
            'question' => 'Which Acme branch should I use?',
            'summary' => 'A branch is required.',
        ]]));
        app(OperatorTaskOrchestrator::class)->run($task->id);

        $this->actingAs($dev)->postJson(route('admin.ai-operator.tasks.resume', $task), [
            'message' => 'Use the Bloemfontein branch.',
        ])->assertOk()->assertJsonPath('status', OperatorTask::STATUS_PLANNED);
        $this->assertDatabaseHas('operator_messages', [
            'operator_conversation_id' => $task->operator_conversation_id,
            'role' => 'user',
            'content' => 'Use the Bloemfontein branch.',
        ]);

        $criticalTask = $this->createTask($dev, 'Publish another article.');
        $criticalTask->update(['status' => OperatorTask::STATUS_WAITING_FOR_APPROVAL]);
        $criticalTask->steps()->create([
            'position' => 1,
            'action' => 'tool',
            'tool' => 'content.apply_article_status',
            'risk' => 'R2',
            'status' => OperatorTask::STATUS_WAITING_FOR_APPROVAL,
            'arguments' => ['article_id' => 999, 'status' => 'published', 'reason' => 'No longer required.'],
        ]);

        $this->postJson(route('admin.ai-operator.tasks.reject', $criticalTask), [
            'reason' => 'Do not publish this.',
        ])->assertOk()->assertJsonPath('status', OperatorTask::STATUS_CANCELLED);
    }

    public function test_developer_workspace_uses_chat_as_the_primary_task_interface(): void
    {
        config([
            'ai_platform.operator.enabled' => true,
            'ai_platform.operator.agent_enabled' => true,
        ]);
        $dev = User::factory()->create(['role' => 'dev']);

        $this->actingAs($dev)->get(route('admin.ai-operator.index'))
            ->assertOk()
            ->assertSee('Developer task')
            ->assertSee('What should I research, create, update, or inspect?')
            ->assertSee('Manual tool runner')
            ->assertSee(route('admin.ai-operator.tasks.store'), false);
    }

    private function createTask(User $user, string $goal): OperatorTask
    {
        $conversation = OperatorConversation::create([
            'user_id' => $user->id,
            'title' => $goal,
            'last_activity_at' => now(),
        ]);

        return $conversation->tasks()->create([
            'user_id' => $user->id,
            'goal' => $goal,
            'status' => OperatorTask::STATUS_PLANNED,
            'step_limit' => 12,
            'usage' => ['steps' => 0, 'cost' => 0],
        ]);
    }
}

class SequenceTaskPlanner implements OperatorTaskPlanner
{
    public function __construct(private array $actions) {}

    public function nextAction(OperatorTask $task, User $user, array $tools): array
    {
        return array_shift($this->actions) ?? ['action' => 'complete', 'summary' => 'Done.'];
    }
}
