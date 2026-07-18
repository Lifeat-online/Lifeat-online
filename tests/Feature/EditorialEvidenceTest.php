<?php

namespace Tests\Feature;

use App\Ai\Editorial\Contracts\HostResolver;
use App\Ai\Editorial\SecureSourceFetcher;
use App\Models\ClaimEvidence;
use App\Models\EditorialClaim;
use App\Models\EditorialDossier;
use App\Models\ResearchItem;
use App\Models\ResearchSource;
use App\Models\SourceSnapshot;
use App\Models\StoryCluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EditorialEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_secure_fetch_creates_immutable_snapshot_and_rejects_private_hosts(): void
    {
        $this->app->instance(HostResolver::class, new class implements HostResolver
        {
            public function addresses(string $host): array
            {
                return $host === 'news.example.com' ? ['93.184.216.34'] : ['127.0.0.1'];
            }
        });
        Http::fake(['https://news.example.com/story' => Http::response(
            '<html><script>ignore()</script><body>Verified council meeting on Friday.</body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        )]);

        $item = $this->researchItem('https://news.example.com/story', 'https://news.example.com/feed');
        $snapshot = app(SecureSourceFetcher::class)->snapshot($item);

        $this->assertSame('Verified council meeting on Friday.', $snapshot->content);
        $this->assertSame(hash('sha256', $snapshot->content), $snapshot->content_hash);
        $this->expectException(\LogicException::class);
        $snapshot->update(['content' => 'Changed']);
    }

    public function test_secure_fetch_rejects_loopback_before_sending_request(): void
    {
        Http::fake();
        $item = $this->researchItem('https://127.0.0.1/admin', 'https://127.0.0.1/feed');

        try {
            app(SecureSourceFetcher::class)->snapshot($item);
            $this->fail('Loopback source should have been rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('private or reserved', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_high_importance_claim_requires_supporting_evidence(): void
    {
        $item = $this->researchItem('https://news.example.com/story', 'https://news.example.com/feed');
        $snapshot = SourceSnapshot::create([
            'research_item_id' => $item->id,
            'url' => $item->source_url,
            'http_status' => 200,
            'content_type' => 'text/plain',
            'content' => 'The council meeting is on Friday.',
            'content_hash' => hash('sha256', 'The council meeting is on Friday.'),
            'fetched_at' => now(),
        ]);
        $cluster = StoryCluster::create(['title' => 'Council meeting', 'fingerprint' => hash('sha256', 'council-meeting')]);
        $dossier = EditorialDossier::create(['story_cluster_id' => $cluster->id, 'title' => 'Council meeting', 'status' => 'approved', 'approved_at' => now()]);
        $claim = EditorialClaim::create(['editorial_dossier_id' => $dossier->id, 'claim' => 'The meeting is Friday.', 'importance' => 'high']);

        $this->assertFalse($dossier->readyForWriting());

        ClaimEvidence::create([
            'editorial_claim_id' => $claim->id,
            'source_snapshot_id' => $snapshot->id,
            'stance' => 'supports',
            'excerpt' => 'The council meeting is on Friday.',
            'authority_score' => 80,
        ]);

        $this->assertTrue($dossier->readyForWriting());
    }

    public function test_editor_can_review_claim_map_and_approve_supported_dossier(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $item = $this->researchItem('https://news.example.com/story', 'https://news.example.com/feed');
        $snapshot = SourceSnapshot::create([
            'research_item_id' => $item->id,
            'url' => $item->source_url,
            'http_status' => 200,
            'content_type' => 'text/plain',
            'content' => 'The council meeting is on Friday.',
            'content_hash' => hash('sha256', 'The council meeting is on Friday.'),
            'fetched_at' => now(),
        ]);
        $cluster = StoryCluster::create(['title' => 'Council meeting', 'fingerprint' => hash('sha256', 'editorial-ui')]);
        $cluster->researchItems()->attach($item);
        $dossier = EditorialDossier::create(['story_cluster_id' => $cluster->id, 'title' => 'Council meeting']);
        $claim = EditorialClaim::create(['editorial_dossier_id' => $dossier->id, 'claim' => 'The meeting is Friday.', 'importance' => 'high']);

        $this->actingAs($editor)->get(route('admin.editorial-dossiers.show', $dossier))
            ->assertOk()
            ->assertSee('Claim map')
            ->assertSee('The meeting is Friday.')
            ->assertSee('The council meeting is on Friday.');

        $this->post(route('admin.editorial-dossiers.evidence.store', [$dossier, $claim]), [
            'source_snapshot_id' => $snapshot->id,
            'stance' => 'supports',
            'excerpt' => 'The council meeting is on Friday.',
            'authority_score' => 85,
        ])->assertRedirect();

        $this->post(route('admin.editorial-dossiers.approve', $dossier))->assertRedirect();
        $this->assertSame('approved', $dossier->fresh()->status);
        $this->assertSame($editor->id, $dossier->fresh()->approved_by);
    }

    private function researchItem(string $sourceUrl, string $registryUrl): ResearchItem
    {
        $source = ResearchSource::create([
            'name' => 'Allowlisted News',
            'slug' => 'allowlisted-news-'.fake()->unique()->numberBetween(1, 100000),
            'type' => ResearchSource::TYPE_RSS,
            'url' => $registryUrl,
            'is_active' => true,
        ]);

        return ResearchItem::create([
            'research_source_id' => $source->id,
            'source_name' => $source->name,
            'source_type' => $source->type,
            'source_url' => $sourceUrl,
            'title' => 'Council story',
            'summary' => 'Local public-interest report.',
            'fingerprint' => hash('sha256', $sourceUrl.fake()->uuid()),
            'status' => ResearchItem::STATUS_NEW,
        ]);
    }
}
