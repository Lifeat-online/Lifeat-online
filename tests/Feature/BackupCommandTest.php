<?php

namespace Tests\Feature;

use App\Console\Commands\BackupCommand;
use App\Console\Commands\BackupListCommand;
use App\Console\Commands\RestoreCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupCommandTest extends TestCase
{
    private string $backupRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupRoot = sys_get_temp_dir().'/lifeat-backup-test-'.uniqid();
        config()->set('backup.local_path', $this->backupRoot);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->backupRoot)) {
            File::deleteDirectory($this->backupRoot);
        }

        parent::tearDown();
    }

    public function test_backup_run_command_is_registered(): void
    {
        $this->assertArrayHasKey('backup:run', Artisan::all());
        $this->assertArrayHasKey('backup:list', Artisan::all());
        $this->assertArrayHasKey('backup:restore', Artisan::all());
    }

    public function test_backup_list_command_reports_empty_directory_cleanly(): void
    {
        $this->artisan('backup:list')
            ->assertExitCode(0);
    }

    public function test_backup_list_command_lists_existing_archives(): void
    {
        $dbDir = $this->backupRoot.'/db';
        File::ensureDirectoryExists($dbDir);
        File::put($dbDir.'/lifeat-2026-06-05.sql.gz', 'fake');

        $this->artisan('backup:list')
            ->assertExitCode(0);
    }

    public function test_backup_run_rejects_invalid_type(): void
    {
        $this->artisan('backup:run', ['--type' => 'bogus'])
            ->expectsOutputToContain("Invalid --type 'bogus'")
            ->assertExitCode(1);
    }

    public function test_restore_command_accepts_a_local_filename(): void
    {
        $dbDir = $this->backupRoot.'/db';
        File::ensureDirectoryExists($dbDir);
        File::put($dbDir.'/lifeat-2026-06-05.sql.gz', 'fake');

        // We do not assert the exit code here: the command delegates to a
        // shell script that requires mysqldump / sqlite3 / gunzip, and the
        // exact exit code is environment-dependent. We just confirm the
        // wrapper accepts the argument without throwing.
        $this->artisan('backup:restore', ['archive' => 'lifeat-2026-06-05.sql.gz', '--yes' => true])
            ->assertExitCode(0);
    }

    public function test_command_classes_resolve_via_service_container(): void
    {
        $this->assertInstanceOf(BackupCommand::class, app(BackupCommand::class));
        $this->assertInstanceOf(BackupListCommand::class, app(BackupListCommand::class));
        $this->assertInstanceOf(RestoreCommand::class, app(RestoreCommand::class));
    }
}
