<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE writer_applications ALTER COLUMN bank_name DROP NOT NULL');
            DB::statement('ALTER TABLE writer_applications ALTER COLUMN account_holder_name DROP NOT NULL');
            DB::statement('ALTER TABLE writer_applications ALTER COLUMN account_number DROP NOT NULL');
            DB::statement('ALTER TABLE writer_applications ALTER COLUMN branch_code DROP NOT NULL');

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE writer_applications MODIFY bank_name VARCHAR(255) NULL');
            DB::statement('ALTER TABLE writer_applications MODIFY account_holder_name VARCHAR(255) NULL');
            DB::statement('ALTER TABLE writer_applications MODIFY account_number VARCHAR(60) NULL');
            DB::statement('ALTER TABLE writer_applications MODIFY branch_code VARCHAR(30) NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE writer_applications ALTER COLUMN bank_name SET NOT NULL');
            DB::statement('ALTER TABLE writer_applications ALTER COLUMN account_holder_name SET NOT NULL');
            DB::statement('ALTER TABLE writer_applications ALTER COLUMN account_number SET NOT NULL');
            DB::statement('ALTER TABLE writer_applications ALTER COLUMN branch_code SET NOT NULL');

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE writer_applications MODIFY bank_name VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE writer_applications MODIFY account_holder_name VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE writer_applications MODIFY account_number VARCHAR(60) NOT NULL');
            DB::statement('ALTER TABLE writer_applications MODIFY branch_code VARCHAR(30) NOT NULL');
        }
    }
};
