<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('landing_features', function (Blueprint $table) {
            $table->id();
            $table->string('section'); // features | formats
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('badge')->nullable();      // short text fallback (e.g. TU) when no icon image
            $table->string('icon_path')->nullable();  // uploaded image on the public disk
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['section', 'order']);
        });

        // Seed the current landing content so the page isn't empty after migrate.
        $now = now();
        $rows = [];
        $features = [
            ['Auto pagination & margins', 'Roman numerals for front matter, Arabic from page 1, and the right binding margin — every page, automatically.'],
            ['Cite with [[key]]', 'Drop a key inline and it becomes a correctly formatted citation. No manual lettering, no broken numbers.'],
            ['IEEE & APA referencing', 'Switch style with one tap. In-text marks and the reference list re-render and re-number themselves.'],
            ['Auto title page', 'Your title, author, supervisor, module and institution build into a clean cover sheet for TU or London Met.'],
            ['Table of contents', 'A contents page generated from your chapters with page numbers that stay correct as you write.'],
            ['One-click PDF', 'Export a submission-ready PDF with margins, page numbers and references all baked in — ready to upload.'],
        ];
        foreach ($features as $i => [$title, $desc]) {
            $rows[] = ['section' => 'features', 'title' => $title, 'description' => $desc, 'badge' => null, 'order' => $i, 'created_at' => $now, 'updated_at' => $now];
        }

        $formats = [
            ['TU', 'Tribhuvan University', 'Front matter, declaration and chapter numbering precomputed to the TU final-year project report guidelines.'],
            ['LM', 'London Metropolitan', 'Layout, spacing and referencing tuned to the London Met report and dissertation conventions.'],
            ['★', 'Custom', 'Design your own cover with the Cover Designer — fonts, images and your own front-matter pages.'],
        ];
        foreach ($formats as $i => [$badge, $title, $desc]) {
            $rows[] = ['section' => 'formats', 'title' => $title, 'description' => $desc, 'badge' => $badge, 'order' => $i, 'created_at' => $now, 'updated_at' => $now];
        }

        $samples = [
            ['Sample Report for e-commerce website for bca/csit', 'A full BCA/CSIT e-commerce website project report — SRS, system design, ER diagrams and testing.'],
            ['Hospital Management System documentation', 'Modules, use-cases and database design for a hospital management system project.'],
            ['Online Room Finder project report', 'Requirements, architecture and screenshots for an online room finder application.'],
            ['Blood Bank Management System', 'Donor and inventory management documentation with diagrams and conclusions.'],
            ['Hostel Management System (TU format)', 'A hostel management system report laid out to the Tribhuvan University format.'],
            ['Ride Sharing Application', 'Final-year ride sharing application documentation, from problem statement to results.'],
        ];
        foreach ($samples as $i => [$title, $desc]) {
            $rows[] = ['section' => 'samples', 'title' => $title, 'description' => $desc, 'badge' => null, 'order' => $i, 'created_at' => $now, 'updated_at' => $now];
        }

        DB::table('landing_features')->insert($rows);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_features');
    }
};
