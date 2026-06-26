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
        Schema::table('books', function (Blueprint $table) {
            $table->text('reading_notes')->nullable()->after('summary');
            $table->string('lent_by')->nullable()->after('loaned_to');
            $table->json('links')->nullable()->after('reading_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['reading_notes', 'lent_by', 'links']);
        });
    }
};
