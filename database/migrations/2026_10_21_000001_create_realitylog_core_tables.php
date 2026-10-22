<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('interface_locale', 5)->default('en');
            $table->string('ai_locale', 5)->default('en');
            $table->string('timezone', 64)->default('Asia/Dhaka');
            $table->string('country', 2)->default('BD');
            $table->string('currency', 3)->default('BDT');
            $table->string('date_format', 20)->default('d M Y');
            $table->string('theme', 10)->default('light');
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('email_reminders')->default(true);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->boolean('external_ai_consent')->default(false);
            $table->timestamps();
        });

        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('memory_type', 40)->default('note');
            $table->string('source_type', 20)->default('text');
            $table->string('source_url', 2048)->nullable();
            $table->longText('body')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->text('summary')->nullable();
            $table->string('detected_language', 8)->default('en');
            $table->date('event_date')->nullable();
            $table->timestamp('captured_at')->useCurrent();
            $table->string('processing_status', 30)->default('queued');
            $table->text('processing_error')->nullable();
            $table->json('ai_metadata')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->string('visibility', 20)->default('private');
            $table->timestamps();

            $table->index(['user_id', 'event_date']);
            $table->index(['user_id', 'memory_type']);
            $table->index(['user_id', 'processing_status']);
            $table->index(['user_id', 'is_archived']);
        });

        Schema::create('memory_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memory_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('kind', 30)->default('document');
            $table->longText('extracted_text')->nullable();
            $table->string('status', 30)->default('stored');
            $table->timestamps();
        });

        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('name');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'type', 'name']);
        });

        Schema::create('memory_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->string('provenance', 20)->default('extracted');
            $table->decimal('confidence', 3, 2)->default(0.75);
            $table->timestamps();

            $table->unique(['memory_id', 'entity_id']);
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('#D95F3B');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('collection_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('memory_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['collection_id', 'memory_id']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('#68756C');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('memory_tag', function (Blueprint $table) {
            $table->foreignId('memory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['memory_id', 'tag_id']);
        });

        Schema::create('memory_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_memory_id')->constrained('memories')->cascadeOnDelete();
            $table->foreignId('to_memory_id')->constrained('memories')->cascadeOnDelete();
            $table->string('relationship_type', 40)->default('related');
            $table->string('provenance', 20)->default('user');
            $table->boolean('is_confirmed')->default(true);
            $table->timestamps();

            $table->unique(['from_memory_id', 'to_memory_id', 'relationship_type']);
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('memory_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('type', 30)->default('custom');
            $table->dateTime('due_at');
            $table->string('status', 20)->default('upcoming');
            $table->string('notification_channel', 20)->default('in_app');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'due_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('processing_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memory_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('status', 20)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('search_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('query', 500);
            $table->string('locale', 5)->default('en');
            $table->unsignedInteger('results_count')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('deletion_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('queued');
            $table->json('scope')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_jobs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('search_histories');
        Schema::dropIfExists('processing_jobs');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('memory_relationships');
        Schema::dropIfExists('memory_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('collection_memories');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('memory_entities');
        Schema::dropIfExists('entities');
        Schema::dropIfExists('memory_attachments');
        Schema::dropIfExists('memories');
        Schema::dropIfExists('user_settings');
    }
};
