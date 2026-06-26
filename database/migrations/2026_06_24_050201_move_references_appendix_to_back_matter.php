<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reports created before References/Appendix became unnumbered back matter
     * still have them as numbered "body" chapters (e.g. "3. References"). Move
     * any such section to the "back" placement so it renders correctly, and drop
     * exact-title duplicates within a report (keeping the first).
     */
    public function up(): void
    {
        $titles = ['references', 'bibliography', 'appendix'];

        // Promote matching body sections to back matter.
        DB::table('sections')
            ->where('placement', 'body')
            ->whereIn(DB::raw('LOWER(TRIM(title))'), $titles)
            ->update(['placement' => 'back']);

        // De-duplicate: if a report now has more than one section with the same
        // (case-insensitive) title, keep the lowest id and delete the rest.
        $dupes = DB::table('sections')
            ->select('report_id', DB::raw('LOWER(TRIM(title)) as t'), DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as c'))
            ->whereIn(DB::raw('LOWER(TRIM(title))'), $titles)
            ->groupBy('report_id', 't')
            ->having('c', '>', 1)
            ->get();

        foreach ($dupes as $dupe) {
            DB::table('sections')
                ->where('report_id', $dupe->report_id)
                ->whereRaw('LOWER(TRIM(title)) = ?', [$dupe->t])
                ->where('id', '!=', $dupe->keep_id)
                ->delete();
        }
    }

    public function down(): void
    {
        // Non-reversible data fix.
    }
};
