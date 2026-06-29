<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An optional admin-uploaded PDF shown via a "View PDF" button on the
     * sample. Only used by the samples section.
     */
    public function up(): void
    {
        Schema::table('landing_features', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('icon_path');
        });
    }

    public function down(): void
    {
        Schema::table('landing_features', function (Blueprint $table) {
            $table->dropColumn('pdf_path');
        });
    }
};
