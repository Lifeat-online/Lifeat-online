<?php

namespace Tests\Feature;

use App\Models\ResearchItem;
use App\Models\ResearchSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResearchCollectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_research_collect_command_seeds_defaults_and_deduplicates_google_news_items(): void
    {
        Http::fake([
            'https://news.google.com/rss/search*' => Http::response($this->rssFeed([
                [
                    'title' => 'Bethlehem water repair update',
                    'link' => 'https://example.com/bethlehem-water?utm_source=google',
                    'guid' => 'story-1',
                    'description' => 'Free State teams are repairing water infrastructure in Bethlehem.',
                    'pubDate' => 'Sat, 23 May 2026 08:00:00 +0200',
                    'source' => 'Example News',
                ],
                [
                    'title' => 'Dihlabeng council meeting scheduled',
                    'link' => 'https://example.com/dihlabeng-council',
                    'guid' => 'story-2',
                    'description' => 'The Dihlabeng Local Municipality meeting is scheduled for next week.',
                    'pubDate' => 'Sat, 23 May 2026 09:00:00 +0200',
                    'source' => 'Municipal Desk',
                ],
            ]), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $this->artisan('life:research:collect --seed --source=google-news-bethlehem-free-state --limit=10')
            ->expectsOutputToContain('Research sources seeded:')
            ->expectsOutputToContain('Research collection complete: 2 new item(s), 0 duplicate(s), 0 failed source(s).')
            ->assertExitCode(0);

        $this->assertDatabaseHas('research_sources', [
            'slug' => 'google-news-bethlehem-free-state',
            'type' => ResearchSource::TYPE_GOOGLE_NEWS_RSS,
        ]);
        $this->assertDatabaseHas('research_items', [
            'title' => 'Bethlehem water repair update',
            'source_name' => 'Example News',
            'source_type' => ResearchSource::TYPE_GOOGLE_NEWS_RSS,
            'status' => ResearchItem::STATUS_NEW,
        ]);

        $item = ResearchItem::query()->where('title', 'Bethlehem water repair update')->firstOrFail();
        $this->assertContains('Bethlehem', $item->detected_locations);
        $this->assertContains('Free State', $item->detected_locations);

        $this->artisan('life:research:collect --source=google-news-bethlehem-free-state --limit=10')
            ->expectsOutputToContain('Research collection complete: 0 new item(s), 2 duplicate(s), 0 failed source(s).')
            ->assertExitCode(0);

        $this->assertSame(2, ResearchItem::count());
    }

    public function test_research_collector_supports_configured_rss_feeds(): void
    {
        ResearchSource::create([
            'name' => 'Custom Local Feed',
            'slug' => 'custom-local-feed',
            'type' => ResearchSource::TYPE_RSS,
            'url' => 'https://local.example.com/feed.xml',
            'is_active' => true,
        ]);

        Http::fake([
            'https://local.example.com/feed.xml' => Http::response($this->rssFeed([
                [
                    'title' => 'Clarens festival road closures',
                    'link' => 'https://local.example.com/clarens-festival-road-closures',
                    'guid' => 'clarens-1',
                    'description' => 'Road closures are expected in Clarens during the festival.',
                    'pubDate' => 'Sat, 23 May 2026 10:00:00 +0200',
                    'source' => 'Local Feed',
                ],
            ]), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $this->artisan('life:research:collect --source=custom-local-feed')
            ->expectsOutputToContain('custom-local-feed: 1 new, 0 duplicate(s), 0 skipped, 1 parsed')
            ->assertExitCode(0);

        $this->assertDatabaseHas('research_items', [
            'title' => 'Clarens festival road closures',
            'source_type' => ResearchSource::TYPE_RSS,
            'status' => ResearchItem::STATUS_NEW,
        ]);
    }

    public function test_dry_run_parses_without_writing_research_items(): void
    {
        ResearchSource::create([
            'name' => 'Dry Run Feed',
            'slug' => 'dry-run-feed',
            'type' => ResearchSource::TYPE_RSS,
            'url' => 'https://local.example.com/dry.xml',
            'is_active' => true,
        ]);

        Http::fake([
            'https://local.example.com/dry.xml' => Http::response($this->rssFeed([
                [
                    'title' => 'Reitz school fundraiser',
                    'link' => 'https://local.example.com/reitz-school-fundraiser',
                    'guid' => 'reitz-1',
                    'description' => 'A school fundraiser is planned in Reitz.',
                    'pubDate' => 'Sat, 23 May 2026 11:00:00 +0200',
                    'source' => 'Local Feed',
                ],
            ]), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $this->artisan('life:research:collect --source=dry-run-feed --dry-run')
            ->expectsOutputToContain('dry-run-feed: 1 new, 0 duplicate(s), 0 skipped, 1 parsed')
            ->assertExitCode(0);

        $this->assertSame(0, ResearchItem::count());
    }

    private function rssFeed(array $items): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Test Feed</title>';

        foreach ($items as $item) {
            $xml .= '<item>';
            $xml .= '<title>'.e($item['title']).'</title>';
            $xml .= '<link>'.e($item['link']).'</link>';
            $xml .= '<guid>'.e($item['guid']).'</guid>';
            $xml .= '<description>'.e($item['description']).'</description>';
            $xml .= '<pubDate>'.e($item['pubDate']).'</pubDate>';
            $xml .= '<source>'.e($item['source']).'</source>';
            $xml .= '</item>';
        }

        return $xml.'</channel></rss>';
    }
}
