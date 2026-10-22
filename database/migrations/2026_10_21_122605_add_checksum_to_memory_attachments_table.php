<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memory_attachments', function (Blueprint $table) {
            $table->string('checksum', 64)->nullable()->after('size');
            $table->unsignedBigInteger('duplicate_of_attachment_id')->nullable()->after('checksum');

            $table->index(['checksum', 'size']);
            $table->index('duplicate_of_attachment_id');
        });
    }

    public function down(): void
    {
        Schema::table('memory_attachments', function (Blueprint $table) {
            $table->dropIndex(['checksum', 'size']);
            $table->dropIndex(['duplicate_of_attachment_id']);
            $table->dropColumn(['checksum', 'duplicate_of_attachment_id']);
        });
    }
};
