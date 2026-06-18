<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Seed the landing FAQ accordion (title = question, description = answer)
        // so the page isn't empty. Idempotent — skip if FAQs already exist.
        if (DB::table('landing_features')->where('section', 'faqs')->exists()) {
            return;
        }

        $now = now();
        $faqs = [
            ['What is a final-year report generator?', 'It turns your chapters and sources into a fully formatted academic report — applying citations, references, page margins, pagination, a title page and a table of contents automatically — then exports a submission-ready PDF.'],
            ['Does it support both IEEE and APA referencing?', 'Yes. Pick the style and every in-text citation and the reference list re-format and re-number instantly.'],
            ['How do I add a citation?', 'Type [[key]] where you want the citation; add the matching source in the references manager and it renders correctly.'],
            ['Can I export the report as a PDF?', 'Yes — one click produces a print-ready PDF with margins, page numbers and references baked in.'],
            ['Do I need to install anything?', 'No. It runs entirely in your browser — nothing to install or configure.'],
        ];

        $rows = [];
        foreach ($faqs as $i => [$question, $answer]) {
            $rows[] = [
                'section' => 'faqs',
                'title' => $question,
                'description' => $answer,
                'badge' => null,
                'visible' => true,
                'order' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('landing_features')->insert($rows);
    }

    public function down(): void
    {
        DB::table('landing_features')->where('section', 'faqs')->delete();
    }
};
