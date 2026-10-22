<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->json('embedding')->nullable()->after('ai_metadata');
            $table->string('embedding_model')->nullable()->after('embedding');
            $table->unsignedSmallInteger('embedding_dimensions')->nullable()->after('embedding_model');
            $table->timestamp('embedded_at')->nullable()->after('embedding_dimensions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'embedding_model', 'embedding_dimensions', 'embedded_at']);
        });
    }
};
