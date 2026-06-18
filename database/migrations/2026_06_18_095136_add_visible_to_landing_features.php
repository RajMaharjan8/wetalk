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
        Schema::table('landing_features', function (Blueprint $table) {
            // Whether the card is shown on the public landing page. Lets admins
            // hide a sample (or any card) without deleting its content.
            $table->boolean('visible')->default(true)->after('link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('landing_features', function (Blueprint $table) {
            $table->dropColumn('visible');
        });
    }
};
