<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a short "excerpt" shown on the card, distinct from the longer
     * "description" shown on the sample detail view. Seed it from the existing
     * description so nothing looks empty after the change.
     */
    public function up(): void
    {
        Schema::table('landing_features', function (Blueprint $table) {
            $table->text('excerpt')->nullable()->after('title');
        });

        DB::table('landing_features')
            ->whereNull('excerpt')
            ->update(['excerpt' => DB::raw('description')]);
    }

    public function down(): void
    {
        Schema::table('landing_features', function (Blueprint $table) {
            $table->dropColumn('excerpt');
        });
    }
};
