<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the consumer-complaint subsystem, restaurants, roles (customer /
 * officer / admin), rewards, and audit logging on top of the original
 * restaurant-review schema. Safe to run on an existing database.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- users: roles + activation + reward points -----------------
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('customer')->change();
        });
        DB::table('users')->where('role', 'user')->update(['role' => 'customer']);

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (! Schema::hasColumn('users', 'points')) {
                $table->unsignedInteger('points')->default(0);
            }
            if (! Schema::hasColumn('users', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }
            if (! Schema::hasColumn('users', 'deactivated_at')) {
                $table->dateTime('deactivated_at')->nullable();
            }
        });

        // ---- restaurants ------------------------------------------------
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('name_hash', 64)->index();
            $table->string('address', 255)->nullable();
            $table->string('district', 60)->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('trust_score', 3, 2)->default(5.00);
            $table->unsignedInteger('verified_complaints')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::table('reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('reviews', 'restaurant_id')) {
                $table->unsignedBigInteger('restaurant_id')->nullable()->index();
            }
        });

        // ---- complaints -------------------------------------------------
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->nullable()->unique();
            $table->string('public_token', 64)->nullable();
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('category', 40)->index();
            $table->string('auto_category', 40)->nullable();
            $table->decimal('category_confidence', 4, 2)->nullable();
            $table->longText('title');          // RSA-OAEP encrypted
            $table->longText('description');    // RSA-OAEP encrypted
            $table->date('incident_date')->nullable();
            $table->string('district', 60)->index();
            $table->string('status', 40)->default('submitted')->index();
            $table->unsignedBigInteger('assigned_officer_id')->nullable()->index();
            $table->unsignedBigInteger('duplicate_of_id')->nullable();
            $table->string('outcome', 255)->nullable();   // public, anonymised outcome
            $table->string('mac', 64)->nullable();
            $table->json('signature')->nullable();
            $table->string('report_sha256', 64)->nullable();
            $table->dateTime('report_generated_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('complaint_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->longText('note')->nullable();          // ECIES encrypted
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('complaint_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->unsignedBigInteger('uploaded_by');
            $table->string('type', 20);                     // image|video|audio|receipt
            $table->longText('original_name');              // ECIES encrypted
            $table->string('stored_path', 255);
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->decimal('blur_score', 10, 2)->nullable();
            $table->boolean('is_blurry')->default(false);
            $table->longText('ocr_text')->nullable();       // ECIES encrypted
            $table->string('ocr_restaurant', 200)->nullable();
            $table->string('ocr_date', 40)->nullable();
            $table->string('ocr_amount', 40)->nullable();
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('complaint_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->unsignedBigInteger('sender_id');
            $table->longText('body');                       // ECIES encrypted
            $table->string('mac', 64);
            $table->dateTime('read_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('complaint_duplicates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->unsignedBigInteger('candidate_id');
            $table->decimal('score', 4, 2);
            $table->string('status', 20)->default('suggested');   // suggested|linked|dismissed
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamps();
            $table->unique(['complaint_id', 'candidate_id']);
        });

        // ---- rewards ----------------------------------------------------
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('badge', 60);
            $table->dateTime('awarded_at')->useCurrent();
            $table->unique(['user_id', 'badge']);
        });

        Schema::create('reward_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->integer('points');
            $table->string('reason', 120);
            $table->unsignedBigInteger('complaint_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });

        // ---- audit log --------------------------------------------------
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('action', 80)->index();
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('reward_events');
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('complaint_duplicates');
        Schema::dropIfExists('complaint_messages');
        Schema::dropIfExists('complaint_evidence');
        Schema::dropIfExists('complaint_status_histories');
        Schema::dropIfExists('complaints');
        Schema::table('reviews', fn (Blueprint $t) => $t->dropColumn('restaurant_id'));
        Schema::dropIfExists('restaurants');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['is_active', 'points', 'created_by', 'deactivated_at']));
    }
};
