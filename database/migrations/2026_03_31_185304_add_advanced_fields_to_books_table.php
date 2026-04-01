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
            $table->string('cover_image')->nullable()->after('description');
            $table->string('download_link')->nullable()->after('cover_image');
            $table->integer('pages')->nullable()->after('download_link');
            $table->string('recommended_by')->nullable()->after('pages');
            $table->string('loaned_to')->nullable()->after('recommended_by');
            $table->foreignId('author_id')->nullable()->after('author')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->dropColumn([
                'cover_image',
                'download_link',
                'pages',
                'recommended_by',
                'loaned_to',
                'author_id'
            ]);
        });
    }
};
