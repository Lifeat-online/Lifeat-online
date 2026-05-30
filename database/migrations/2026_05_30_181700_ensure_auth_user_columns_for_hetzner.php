<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'name')) {
                    $table->string('name')->nullable();
                }

                if (! Schema::hasColumn('users', 'email')) {
                    $table->string('email')->nullable()->unique();
                }

                if (! Schema::hasColumn('users', 'email_verified_at')) {
                    $table->timestamp('email_verified_at')->nullable();
                }

                if (! Schema::hasColumn('users', 'password')) {
                    $table->string('password')->nullable();
                }

                if (! Schema::hasColumn('users', 'remember_token')) {
                    $table->rememberToken();
                }

                if (! Schema::hasColumn('users', 'created_at') && ! Schema::hasColumn('users', 'updated_at')) {
                    $table->timestamps();
                } elseif (! Schema::hasColumn('users', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                } elseif (! Schema::hasColumn('users', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }

                if (! Schema::hasColumn('users', 'role')) {
                    $table->string('role')->default('member');
                }

                if (! Schema::hasColumn('users', 'phone')) {
                    $table->string('phone')->nullable();
                }

                if (! Schema::hasColumn('users', 'bio')) {
                    $table->text('bio')->nullable();
                }

                if (! Schema::hasColumn('users', 'username')) {
                    $table->string('username')->nullable()->unique();
                }

                if (! Schema::hasColumn('users', 'preferred_locale')) {
                    $table->string('preferred_locale', 12)->nullable()->index();
                }
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        // This migration only repairs legacy production schema drift.
    }
};
