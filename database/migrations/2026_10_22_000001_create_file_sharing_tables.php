<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('allow_download')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shared_file_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_file_id')->constrained('shared_files')->cascadeOnDelete();
            // Set when the recipient is an internal HRMS user (they still verify by email code).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->boolean('can_comment')->default(true);
            // Link token: sha256 for lookup, encrypted copy so invites can be re-sent.
            $table->char('token_hash', 64)->unique();
            $table->text('token');
            $table->string('otp_hash')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['shared_file_id', 'email']);
            $table->index('email');
        });

        Schema::create('shared_file_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_file_id')->constrained('shared_files')->cascadeOnDelete();
            // Always the thread's root comment — replies are one level deep.
            $table->foreignId('parent_id')->nullable()->constrained('shared_file_comments')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('shared_file_recipients')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Snapshot so the thread still reads right if the recipient/user is removed.
            $table->string('author_name');
            $table->string('author_email');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('shared_file_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_file_id')->constrained('shared_files')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('shared_file_recipients')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['shared_file_id', 'created_at']);
        });

        // Permission for HR/admins to see and manage every user's shared files.
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->insertOrIgnore([
                'name' => 'fileshare.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
            ]);

            $permissionId = DB::table('permissions')->where(['name' => 'fileshare.manage', 'guard_name' => 'web'])->value('id');
            $hrAdminId = DB::table('roles')->where(['name' => 'HR Admin', 'guard_name' => 'web'])->value('id');

            if ($permissionId && $hrAdminId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $hrAdminId]);
            }

            app()['cache']->forget(config('permission.cache.key', 'spatie.permission.cache'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_file_access_logs');
        Schema::dropIfExists('shared_file_comments');
        Schema::dropIfExists('shared_file_recipients');
        Schema::dropIfExists('shared_files');

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('name', 'fileshare.manage')->delete();
        }
    }
};
