<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
            $table->string('phone', 50)->nullable()->after('email');
            $table->timestamp('welcome_sent_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('seo_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('share_image')->nullable();
            $table->json('blocks')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('show_in_menu')->default(false);
            $table->integer('menu_order')->default(0);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('mail_templates', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('subject');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45)->index();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('channel', 20)->default('admin');
            $table->boolean('successful')->default(false);
            $table->string('reason')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 64)->unique();
            $table->string('reason')->nullable();
            $table->boolean('is_automatic')->default(false);
            $table->timestamp('blocked_until')->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->char('visitor', 16);
            $table->string('ip', 45)->nullable()->index();
            $table->string('path')->index();
            $table->string('source', 20)->index();
            $table->string('source_name', 100)->nullable();
            $table->string('referrer_host')->nullable();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('device', 10)->nullable();
            $table->string('browser', 30)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['created_at', 'visitor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
        Schema::dropIfExists('blocked_ips');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('mail_templates');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('settings');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'phone', 'welcome_sent_at', 'last_login_at', 'last_login_ip']);
        });
    }
};
