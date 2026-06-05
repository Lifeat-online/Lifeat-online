<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        match (DB::getDriverName()) {
            'sqlite' => $this->createSqliteTriggers(),
            'mysql', 'mariadb' => $this->createMysqlTriggers(),
            'pgsql' => $this->createPostgresTriggers(),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'sqlite' => $this->dropSqliteTriggers(),
            'mysql', 'mariadb' => $this->dropMysqlTriggers(),
            'pgsql' => $this->dropPostgresTriggers(),
            default => null,
        };
    }

    private function createSqliteTriggers(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TRIGGER wallet_ledger_entries_no_update
BEFORE UPDATE ON wallet_ledger_entries
FOR EACH ROW
BEGIN
    SELECT RAISE(ABORT, 'wallet ledger entries are append-only');
END;
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER wallet_ledger_entries_no_delete
BEFORE DELETE ON wallet_ledger_entries
FOR EACH ROW
BEGIN
    SELECT RAISE(ABORT, 'wallet ledger entries are append-only');
END;
SQL);
    }

    private function dropSqliteTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS wallet_ledger_entries_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS wallet_ledger_entries_no_delete');
    }

    private function createMysqlTriggers(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TRIGGER wallet_ledger_entries_no_update
BEFORE UPDATE ON wallet_ledger_entries
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wallet ledger entries are append-only'
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER wallet_ledger_entries_no_delete
BEFORE DELETE ON wallet_ledger_entries
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wallet ledger entries are append-only'
SQL);
    }

    private function dropMysqlTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS wallet_ledger_entries_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS wallet_ledger_entries_no_delete');
    }

    private function createPostgresTriggers(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION reject_wallet_ledger_entry_mutation()
RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'wallet ledger entries are append-only';
END;
$$ LANGUAGE plpgsql;
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER wallet_ledger_entries_no_update
BEFORE UPDATE ON wallet_ledger_entries
FOR EACH ROW
EXECUTE FUNCTION reject_wallet_ledger_entry_mutation()
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER wallet_ledger_entries_no_delete
BEFORE DELETE ON wallet_ledger_entries
FOR EACH ROW
EXECUTE FUNCTION reject_wallet_ledger_entry_mutation()
SQL);
    }

    private function dropPostgresTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS wallet_ledger_entries_no_update ON wallet_ledger_entries');
        DB::unprepared('DROP TRIGGER IF EXISTS wallet_ledger_entries_no_delete ON wallet_ledger_entries');
        DB::unprepared('DROP FUNCTION IF EXISTS reject_wallet_ledger_entry_mutation()');
    }
};
