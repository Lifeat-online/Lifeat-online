<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WriterApplication;
use App\Notifications\WriterApplicationApprovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminWriterApplicationReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_writer_application_queue_and_detail_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $application = WriterApplication::create([
            'first_name' => 'Lebo',
            'last_name' => 'Mokoena',
            'email' => 'lebo@example.com',
            'phone' => '082 000 0000',
            'username' => 'lebo_writer',
            'profile_bio' => str_repeat('Community storyteller with strong local business awareness. ', 3),
            'profile_photo_path' => 'writer-applications/profile-photos/lebo.jpg',
            'available_on_whatsapp' => true,
            'sample_article_title' => 'Market growth story',
            'sample_article_body' => str_repeat('This sample article body explains local market impact in practical detail. ', 6),
            'sample_advert_title' => 'Visit Bethlehem Bakery',
            'sample_advert_body' => str_repeat('Fresh bread and a family welcome make this bakery worth visiting. ', 3),
            'id_document_path' => 'writer-applications/id-documents/id.pdf',
            'banking_document_path' => 'writer-applications/banking-documents/bank.pdf',
            'proof_of_residence_path' => 'writer-applications/proof-of-residence/home.pdf',
            'bank_name' => 'Capitec',
            'account_holder_name' => 'Lebo Mokoena',
            'account_number' => '1234567890',
            'branch_code' => '470010',
            'status' => WriterApplication::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.writer-applications.index'))
            ->assertOk()
            ->assertSee('Writer Applications')
            ->assertSee('Lebo Mokoena');

        $this->actingAs($admin)
            ->get(route('admin.writer-applications.show', $application))
            ->assertOk()
            ->assertSee('Review Application')
            ->assertSee('Market growth story')
            ->assertSee('Open ID document');
    }

    public function test_admin_can_open_private_writer_application_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('writer-applications/id-documents/id.pdf', 'private id document');

        $admin = User::factory()->create(['role' => 'admin']);
        $application = WriterApplication::create([
            'first_name' => 'Private',
            'last_name' => 'Applicant',
            'email' => 'private@example.com',
            'phone' => '082 000 0001',
            'username' => 'private_writer',
            'profile_bio' => str_repeat('Private document route coverage for sensitive application files. ', 3),
            'profile_photo_path' => 'writer-applications/profile-photos/private.jpg',
            'available_on_whatsapp' => true,
            'sample_article_title' => 'Private article',
            'sample_article_body' => str_repeat('This sample content supports private document route coverage. ', 6),
            'sample_advert_title' => 'Private advert',
            'sample_advert_body' => str_repeat('This advert sample supports private document route coverage. ', 3),
            'id_document_path' => 'writer-applications/id-documents/id.pdf',
            'banking_document_path' => 'writer-applications/banking-documents/bank.pdf',
            'proof_of_residence_path' => 'writer-applications/proof-of-residence/home.pdf',
            'bank_name' => 'Capitec',
            'account_holder_name' => 'Private Applicant',
            'account_number' => '1234567890',
            'branch_code' => '470010',
            'status' => WriterApplication::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.writer-applications.documents.show', [$application, 'id']))
            ->assertOk()
            ->assertHeader('content-type');
    }

    public function test_admin_review_page_handles_missing_banking_proof_as_payout_later(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $application = WriterApplication::create([
            'first_name' => 'NoBank',
            'last_name' => 'Applicant',
            'email' => 'nobank@example.com',
            'phone' => '082 000 0022',
            'username' => 'nobank_writer',
            'profile_bio' => str_repeat('Applicant whose banking proof will only be collected once a payout is due. ', 3),
            'profile_photo_path' => 'writer-applications/profile-photos/nobank.jpg',
            'available_on_whatsapp' => true,
            'sample_article_title' => 'No bank article',
            'sample_article_body' => str_repeat('This application supports optional banking proof review display. ', 6),
            'sample_advert_title' => 'No bank advert',
            'sample_advert_body' => str_repeat('This advert sample supports payout-later review display. ', 3),
            'id_document_path' => 'writer-applications/id-documents/id.pdf',
            'proof_of_residence_path' => 'writer-applications/proof-of-residence/home.pdf',
            'status' => WriterApplication::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.writer-applications.show', $application))
            ->assertOk()
            ->assertSee('Documents And Payout Readiness')
            ->assertSee('Banking proof not collected yet')
            ->assertSee('payable ledger or staff commission');
    }

    public function test_approval_notification_explains_writer_workspace_and_payout_timing(): void
    {
        $user = User::factory()->create([
            'role' => 'writer',
            'email' => 'mail-writer@example.com',
        ]);
        $application = WriterApplication::create([
            'user_id' => $user->id,
            'first_name' => 'Mail',
            'last_name' => 'Writer',
            'email' => 'mail-writer@example.com',
            'phone' => '082 000 0033',
            'username' => 'mail_writer',
            'profile_bio' => str_repeat('Approved writer notification profile. ', 4),
            'profile_photo_path' => 'writer-applications/profile-photos/mail.jpg',
            'available_on_whatsapp' => true,
            'sample_article_title' => 'Mail article',
            'sample_article_body' => str_repeat('This sample article supports approval notification content. ', 6),
            'sample_advert_title' => 'Mail advert',
            'sample_advert_body' => str_repeat('This sample advert supports approval notification content. ', 3),
            'id_document_path' => 'writer-applications/id-documents/id.pdf',
            'proof_of_residence_path' => 'writer-applications/proof-of-residence/home.pdf',
            'status' => WriterApplication::STATUS_APPROVED,
            'assigned_role' => WriterApplication::ROLE_WRITER,
            'submitted_at' => now()->subDay(),
        ]);

        $mail = (new WriterApplicationApprovedNotification($application, 'reset-token'))->toMail($user);

        $this->assertSame('Your Life Platform application has been approved', $mail->subject);
        $this->assertContains('After signing in, open My Article Submissions to draft your first story, submit it for review, and watch for editor feedback.', $mail->outroLines);
        $this->assertContains('Writer earnings only appear after an article is approved, published, and added to the word ledger. Banking or payout details are handled later through the payout workflow.', $mail->outroLines);
    }

    public function test_admin_queue_shows_access_summary_for_recently_contacted_application(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $application = WriterApplication::create([
            'first_name' => 'Queue',
            'last_name' => 'Applicant',
            'email' => 'queue@example.com',
            'phone' => '082 121 2121',
            'username' => 'queue_writer',
            'profile_bio' => str_repeat('Approved applicant included to verify queue access-summary rendering. ', 3),
            'profile_photo_path' => 'writer-applications/profile-photos/queue.jpg',
            'available_on_whatsapp' => true,
            'sample_article_title' => 'Queue article',
            'sample_article_body' => str_repeat('This approved application validates queue-level access summary visibility. ', 6),
            'sample_advert_title' => 'Queue advert',
            'sample_advert_body' => str_repeat('This advert sample supports the queue access summary test. ', 3),
            'id_document_path' => 'writer-applications/id-documents/id.pdf',
            'banking_document_path' => 'writer-applications/banking-documents/bank.pdf',
            'proof_of_residence_path' => 'writer-applications/proof-of-residence/home.pdf',
            'bank_name' => 'Capitec',
            'account_holder_name' => 'Queue Applicant',
            'account_number' => '1212121212',
            'branch_code' => '470010',
            'status' => WriterApplication::STATUS_APPROVED,
            'assigned_role' => WriterApplication::ROLE_WRITER,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subMinutes(2),
            'onboarded_at' => now()->subMinutes(2),
            'access_notified_at' => now()->subMinutes(2),
        ]);

        AuditLog::create([
            'actor_user_id' => $admin->id,
            'action' => 'writer_application.reviewed',
            'subject_type' => WriterApplication::class,
            'subject_id' => $application->id,
            'before_json' => ['status' => WriterApplication::STATUS_PENDING],
            'after_json' => [
                'status' => WriterApplication::STATUS_APPROVED,
                'access_notified_at' => now()->subMinutes(2)->toDateTimeString(),
            ],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        AuditLog::create([
            'actor_user_id' => $admin->id,
            'action' => 'writer_application.access_resent',
            'subject_type' => WriterApplication::class,
            'subject_id' => $application->id,
            'before_json' => ['access_notified_at' => now()->subMinutes(4)->toDateTimeString()],
            'after_json' => ['access_notified_at' => now()->subMinutes(2)->toDateTimeString()],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.writer-applications.index'))
            ->assertOk()
            ->assertSee('Cooldown until')
            ->assertSee('2 events')
            ->assertSee('Last resent');
    }

    public function test_editor_can_review_writer_application_and_audit_it(): void
    {
        Notification::fake();

        $editor = User::factory()->create(['role' => 'editor']);

        $application = WriterApplication::create([
            'first_name' => 'Thabo',
            'last_name' => 'Ndlovu',
            'email' => 'thabo@example.com',
            'phone' => '083 000 0000',
            'username' => 'thabo_local',
            'profile_bio' => str_repeat('Local writer with strong community contacts and advert drafting experience. ', 3),
            'profile_photo_path' => 'writer-applications/profile-photos/thabo.jpg',
            'available_on_whatsapp' => false,
            'sample_article_title' => 'Free State youth project',
            'sample_article_body' => str_repeat('This writing sample covers a community project with relevant local context and quotes. ', 6),
            'sample_advert_title' => 'Promote your business locally',
            'sample_advert_body' => str_repeat('Reach nearby readers with targeted visibility across the platform. ', 3),
            'id_document_path' => 'writer-applications/id-documents/id.pdf',
            'banking_document_path' => 'writer-applications/banking-documents/bank.pdf',
            'proof_of_residence_path' => 'writer-applications/proof-of-residence/home.pdf',
            'bank_name' => 'Nedbank',
            'account_holder_name' => 'Thabo Ndlovu',
            'account_number' => '9876543210',
            'branch_code' => '198765',
            'status' => WriterApplication::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $this->actingAs($editor)
            ->post(route('admin.writer-applications.review', $application), [
                'status' => WriterApplication::STATUS_APPROVED,
                'assigned_role' => WriterApplication::ROLE_WRITER,
                'admin_notes' => 'Strong submission. Move into writer onboarding next.',
            ])
            ->assertRedirect(route('admin.writer-applications.show', $application));

        $application->refresh();
        $user = User::query()->where('email', 'thabo@example.com')->firstOrFail();

        $this->assertSame(WriterApplication::STATUS_APPROVED, $application->status);
        $this->assertSame(WriterApplication::ROLE_WRITER, $application->assigned_role);
        $this->assertSame('Strong submission. Move into writer onboarding next.', $application->admin_notes);
        $this->assertNotNull($application->reviewed_at);
        $this->assertNotNull($application->onboarded_at);
        $this->assertNotNull($application->access_notified_at);
        $this->assertSame($user->id, $application->user_id);
        $this->assertSame('writer', $user->role);
        $this->assertTrue($user->hasRole('writer'));
        Notification::assertSentTo($user, WriterApplicationApprovedNotification::class);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'writer_application.reviewed',
            'subject_type' => WriterApplication::class,
            'subject_id' => $application->id,
            'actor_user_id' => $editor->id,
        ]);

        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_approving_existing_user_application_adds_staff_role_without_clobbering_business_owner_role(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create([
            'role' => 'business_owner',
            'name' => 'Kamo Sibeko',
            'email' => 'kamo@example.com',
            'username' => 'kamo_biz',
        ]);

        $application = WriterApplication::create([
            'user_id' => $owner->id,
            'first_name' => 'Kamo',
            'last_name' => 'Sibeko',
            'email' => 'kamo@example.com',
            'phone' => '084 000 0000',
            'username' => 'kamo_biz',
            'profile_bio' => str_repeat('Business owner who can also help with local sales outreach and account onboarding. ', 3),
            'profile_photo_path' => 'writer-applications/profile-photos/kamo.jpg',
            'available_on_whatsapp' => true,
            'sample_article_title' => 'Support sample',
            'sample_article_body' => str_repeat('This sample shows local business knowledge and practical communication skills for community promotion. ', 6),
            'sample_advert_title' => 'Grow your reach in Bethlehem',
            'sample_advert_body' => str_repeat('Use the platform to help businesses attract nearby customers and stay visible. ', 3),
            'id_document_path' => 'writer-applications/id-documents/id.pdf',
            'banking_document_path' => 'writer-applications/banking-documents/bank.pdf',
            'proof_of_residence_path' => 'writer-applications/proof-of-residence/home.pdf',
            'bank_name' => 'FNB',
            'account_holder_name' => 'Kamo Sibeko',
            'account_number' => '111122223333',
            'branch_code' => '250655',
            'status' => WriterApplication::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.writer-applications.review', $application), [
                'status' => WriterApplication::STATUS_APPROVED,
                'assigned_role' => WriterApplication::ROLE_STAFF,
                'admin_notes' => 'Approved for sales staff onboarding.',
            ])
            ->assertRedirect(route('admin.writer-applications.show', $application));

        $application->refresh();
        $owner->refresh();

        $this->assertSame($owner->id, $application->user_id);
        $this->assertSame(WriterApplication::ROLE_STAFF, $application->assigned_role);
        $this->assertSame('business_owner', $owner->role);
        $this->assertTrue($owner->hasRole('staff'));
        $this->assertNotNull($application->access_notified_at);
        Notification::assertSentTo($owner, WriterApplicationApprovedNotification::class);
    }

    public function test_updating_notes_on_already_approved_application_does_not_resend_access_email(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'role' => 'writer',
            'email' => 'approved@example.com',
            'username' => 'approved_writer',
        ]);

        $application = WriterApplication::create([
            'user_id' => $user->id,
            'first_name' => 'Approved',
            'last_name' => 'Writer',
            'email' => 'approved@example.com',
            'phone' => '084 111 2222',
            'username' => 'approved_writer',
            'profile_bio' => str_repeat('Already onboarded writer with an approved application record. ', 3),
            'profile_photo_path' => 'writer-applications/profile-photos/approved.jpg',
            'available_on_whatsapp' => true,
            'sample_article_title' => 'Approved sample',
            'sample_article_body' => str_repeat('This application is already approved and should not trigger duplicate access mail on note changes. ', 6),
            'sample_advert_title' => 'Approved advert',
            'sample_advert_body' => str_repeat('This advert sample is already part of an onboarded application record. ', 3),
            'id_document_path' => 'writer-applications/id-documents/id.pdf',
            'banking_document_path' => 'writer-applications/banking-documents/bank.pdf',
            'proof_of_residence_path' => 'writer-applications/proof-of-residence/home.pdf',
            'bank_name' => 'ABSA',
            'account_holder_name' => 'Approved Writer',
            'account_number' => '222233334444',
            'branch_code' => '632005',
            'status' => WriterApplication::STATUS_APPROVED,
            'assigned_role' => WriterApplication::ROLE_WRITER,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subHours(10),
            'onboarded_at' => now()->subHours(10),
            'access_notified_at' => now()->subHours(10),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.writer-applications.review', $application), [
                'status' => WriterApplication::STATUS_APPROVED,
                'assigned_role' => WriterApplication::ROLE_WRITER,
                'admin_notes' => 'Updated notes only.',
            ])
            ->assertRedirect(route('admin.writer-applications.show', $application));

        Notification::assertNothingSent();
    }

    public function test_admin_can_resend_access_email_for_approved_application(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'role' => 'writer',
            'email' => 'resend@example.com',
            'username' => 'resend_writer',
        ]);

        $application = WriterApplication::create([
            'user_id' => $user->id,
            'first_name' => 'Resend',
            'last_name' => 'Applicant',
            'email' => 'resend@example.com',
            'phone' => '082 333 4444',
            'username' => 'resend_writer',
            'profile_bio' => str_repeat('Approved applicant who needs their access email sent again. ', 3),
            'profile_photo_path' => 'writer-applications/profile-photos/resend.jpg',
            'available_on_whatsapp' => true,
            'sample_article_title' => 'Resend article',
            'sample_article_body' => str_repeat('This approved application is used to verify manual access-email resend behavior. ', 6),
            'sample_advert_title' => 'Resend advert',
            'sample_advert_body' => str_repeat('This advert sample supports resend-flow testing for approved applications. ', 3),
            'id_document_path' => 'writer-applications/id-documents/id.pdf',
            'banking_document_path' => 'writer-applications/banking-documents/bank.pdf',
            'proof_of_residence_path' => 'writer-applications/proof-of-residence/home.pdf',
            'bank_name' => 'Standard Bank',
            'account_holder_name' => 'Resend Applicant',
            'account_number' => '333344445555',
            'branch_code' => '051001',
            'status' => WriterApplication::STATUS_APPROVED,
            'assigned_role' => WriterApplication::ROLE_WRITER,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subHours(12),
            'onboarded_at' => now()->subHours(12),
            'access_notified_at' => now()->subHours(12),
        ]);

        $previousNotificationTime = $application->access_notified_at;

        $this->actingAs($admin)
            ->post(route('admin.writer-applications.resend-access', $application))
            ->assertRedirect(route('admin.writer-applications.show', $application));

        $application->refresh();

        $this->assertTrue($application->access_notified_at->gt($previousNotificationTime));
        Notification::assertSentTo($user, WriterApplicationApprovedNotification::class);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'writer_application.access_resent',
            'subject_type' => WriterApplication::class,
            'subject_id' => $application->id,
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_approved_application_detail_shows_resend_cooldown_message_when_recently_notified(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'role' => 'writer',
            'email' => 'cooldown@example.com',
            'username' => 'cooldown_writer',
        ]);

        $application = WriterApplication::create([
            'user_id' => $user->id,
            'first_name' => 'Cooldown',
            'last_name' => 'Applicant',
            'email' => 'cooldown@example.com',
            'phone' => '082 444 5555',
            'username' => 'cooldown_writer',
            'profile_bio' => str_repeat('Approved applicant with a very recent access email. ', 3),
            'profile_photo_path' => 'writer-applications/profile-photos/cooldown.jpg',
            'available_on_whatsapp' => true,
            'sample_article_title' => 'Cooldown article',
            'sample_article_body' => str_repeat('This approved application verifies resend cooldown messaging in the review UI. ', 6),
            'sample_advert_title' => 'Cooldown advert',
            'sample_advert_body' => str_repeat('This advert sample supports cooldown UI verification. ', 3),
            'id_document_path' => 'writer-applications/id-documents/id.pdf',
            'banking_document_path' => 'writer-applications/banking-documents/bank.pdf',
            'proof_of_residence_path' => 'writer-applications/proof-of-residence/home.pdf',
            'bank_name' => 'Discovery Bank',
            'account_holder_name' => 'Cooldown Applicant',
            'account_number' => '444455556666',
            'branch_code' => '679000',
            'status' => WriterApplication::STATUS_APPROVED,
            'assigned_role' => WriterApplication::ROLE_WRITER,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subMinutes(2),
            'onboarded_at' => now()->subMinutes(2),
            'access_notified_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.writer-applications.show', $application))
            ->assertOk()
            ->assertSee('Resend available after');
    }

    public function test_approved_application_detail_shows_access_notification_history(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'History Admin',
        ]);

        $application = WriterApplication::create([
            'first_name' => 'History',
            'last_name' => 'Applicant',
            'email' => 'history@example.com',
            'phone' => '082 777 8888',
            'username' => 'history_writer',
            'profile_bio' => str_repeat('Approved applicant with multiple access notification events recorded. ', 3),
            'profile_photo_path' => 'writer-applications/profile-photos/history.jpg',
            'available_on_whatsapp' => true,
            'sample_article_title' => 'History article',
            'sample_article_body' => str_repeat('This approved application verifies access-notification history rendering in the admin review screen. ', 6),
            'sample_advert_title' => 'History advert',
            'sample_advert_body' => str_repeat('This advert sample supports the notification history UI test. ', 3),
            'id_document_path' => 'writer-applications/id-documents/id.pdf',
            'banking_document_path' => 'writer-applications/banking-documents/bank.pdf',
            'proof_of_residence_path' => 'writer-applications/proof-of-residence/home.pdf',
            'bank_name' => 'First National',
            'account_holder_name' => 'History Applicant',
            'account_number' => '777788889999',
            'branch_code' => '250655',
            'status' => WriterApplication::STATUS_APPROVED,
            'assigned_role' => WriterApplication::ROLE_WRITER,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subHours(5),
            'onboarded_at' => now()->subHours(5),
            'access_notified_at' => now()->subHours(1),
        ]);

        AuditLog::create([
            'actor_user_id' => $admin->id,
            'action' => 'writer_application.reviewed',
            'subject_type' => WriterApplication::class,
            'subject_id' => $application->id,
            'before_json' => ['status' => WriterApplication::STATUS_PENDING],
            'after_json' => [
                'status' => WriterApplication::STATUS_APPROVED,
                'access_notified_at' => now()->subHours(5)->toDateTimeString(),
            ],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        AuditLog::create([
            'actor_user_id' => $admin->id,
            'action' => 'writer_application.access_resent',
            'subject_type' => WriterApplication::class,
            'subject_id' => $application->id,
            'before_json' => ['access_notified_at' => now()->subHours(5)->toDateTimeString()],
            'after_json' => ['access_notified_at' => now()->subHour()->toDateTimeString()],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.writer-applications.show', $application))
            ->assertOk()
            ->assertSee('Access Notification History')
            ->assertSee('Access email sent')
            ->assertSee('Access email resent')
            ->assertSee('History Admin');
    }

    public function test_admin_cannot_resend_access_email_during_cooldown(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'role' => 'writer',
            'email' => 'blocked@example.com',
            'username' => 'blocked_writer',
        ]);

        $application = WriterApplication::create([
            'user_id' => $user->id,
            'first_name' => 'Blocked',
            'last_name' => 'Applicant',
            'email' => 'blocked@example.com',
            'phone' => '082 555 6666',
            'username' => 'blocked_writer',
            'profile_bio' => str_repeat('Approved applicant still within the resend cooldown window. ', 3),
            'profile_photo_path' => 'writer-applications/profile-photos/blocked.jpg',
            'available_on_whatsapp' => true,
            'sample_article_title' => 'Blocked article',
            'sample_article_body' => str_repeat('This approved application verifies resend cooldown enforcement on the server side. ', 6),
            'sample_advert_title' => 'Blocked advert',
            'sample_advert_body' => str_repeat('This advert sample supports resend cooldown validation testing. ', 3),
            'id_document_path' => 'writer-applications/id-documents/id.pdf',
            'banking_document_path' => 'writer-applications/banking-documents/bank.pdf',
            'proof_of_residence_path' => 'writer-applications/proof-of-residence/home.pdf',
            'bank_name' => 'TymeBank',
            'account_holder_name' => 'Blocked Applicant',
            'account_number' => '555566667777',
            'branch_code' => '678910',
            'status' => WriterApplication::STATUS_APPROVED,
            'assigned_role' => WriterApplication::ROLE_WRITER,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subMinutes(1),
            'onboarded_at' => now()->subMinutes(1),
            'access_notified_at' => now()->subMinutes(1),
        ]);

        $this->from(route('admin.writer-applications.show', $application))
            ->actingAs($admin)
            ->post(route('admin.writer-applications.resend-access', $application))
            ->assertRedirect(route('admin.writer-applications.show', $application))
            ->assertSessionHasErrors('access_notified_at');

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'writer_application.access_resent',
            'subject_type' => WriterApplication::class,
            'subject_id' => $application->id,
        ]);
    }
}
