<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->index(['user_id', 'is_archived', 'captured_at'], 'memories_user_id_is_archived_captured_at_index');
            $table->index(['user_id', 'is_archived', 'memory_type'], 'memories_user_id_is_archived_memory_type_index');
        });

        Schema::table('memory_attachments', function (Blueprint $table) {
            $table->index('memory_id', 'memory_attachments_memory_id_index');
        });

        Schema::table('memory_entities', function (Blueprint $table) {
            $table->index('entity_id', 'memory_entities_entity_id_index');
        });

        Schema::table('memory_tag', function (Blueprint $table) {
            $table->index('tag_id', 'memory_tag_tag_id_index');
        });

        Schema::table('collection_memories', function (Blueprint $table) {
            $table->index('memory_id', 'collection_memories_memory_id_index');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->index(['user_id', 'name'], 'collections_user_id_name_index');
        });

        Schema::table('memory_relationships', function (Blueprint $table) {
            $table->index('to_memory_id', 'memory_relationships_to_memory_id_index');
            $table->index(['user_id', 'is_confirmed'], 'memory_relationships_user_confirmed_index');
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->index('memory_id', 'reminders_memory_id_index');
            $table->index(
                ['status', 'notification_channel', 'email_sent_at', 'due_at'],
                'reminders_due_email_index'
            );
        });

        Schema::table('processing_jobs', function (Blueprint $table) {
            $table->index('memory_id', 'processing_jobs_memory_id_index');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('created_at', 'audit_logs_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_created_at_index');
        });

        Schema::table('processing_jobs', function (Blueprint $table) {
            $table->dropIndex('processing_jobs_memory_id_index');
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->dropIndex('reminders_due_email_index');
            $table->dropIndex('reminders_memory_id_index');
        });

        Schema::table('memory_relationships', function (Blueprint $table) {
            $table->dropIndex('memory_relationships_user_confirmed_index');
            $table->dropIndex('memory_relationships_to_memory_id_index');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex('collections_user_id_name_index');
        });

        Schema::table('collection_memories', function (Blueprint $table) {
            $table->dropIndex('collection_memories_memory_id_index');
        });

        Schema::table('memory_tag', function (Blueprint $table) {
            $table->dropIndex('memory_tag_tag_id_index');
        });

        Schema::table('memory_entities', function (Blueprint $table) {
            $table->dropIndex('memory_entities_entity_id_index');
        });

        Schema::table('memory_attachments', function (Blueprint $table) {
            $table->dropIndex('memory_attachments_memory_id_index');
        });

        Schema::table('memories', function (Blueprint $table) {
            $table->dropIndex('memories_user_id_is_archived_memory_type_index');
            $table->dropIndex('memories_user_id_is_archived_captured_at_index');
        });
    }
};
