<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Seed the "How it works" steps (title + description; the number is the
        // row order) so the section isn't empty on existing installs.
        // Idempotent — skip if steps already exist.
        if (DB::table('landing_features')->where('section', 'steps')->exists()) {
            return;
        }

        $now = now();
        $steps = [
            ['Add your details', 'Enter the title, author, guide and department once. Your title page and front matter build themselves.'],
            ['Write & cite', 'Write your chapters, add your sources, and reference them inline with [[key]]. The preview updates live.'],
            ['Export the PDF', 'Pick IEEE or APA, choose your margins, and download a polished, submission-ready document.'],
        ];

        $rows = [];
        foreach ($steps as $i => [$title, $body]) {
            $rows[] = [
                'section' => 'steps',
                'title' => $title,
                'description' => $body,
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
        DB::table('landing_features')->where('section', 'steps')->delete();
    }
};
