<?php

namespace Database\Seeders;

use App\Models\LandingFeature;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SampleReportSeeder extends Seeder
{
    /**
     * Generate public, viewable sample reports for the landing-page sample
     * cards. Idempotent — safe to run repeatedly (e.g. on deploy):
     *
     *   php artisan db:seed --class=SampleReportSeeder
     */
    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'sample-library@reportgenerator.local'],
            ['name' => 'Sample Library', 'password' => Hash::make(Str::random(40)), 'email_verified_at' => now()],
        );

        $cards = LandingFeature::section('samples')->get();

        foreach ($this->samples() as $i => $sample) {
            $report = Report::updateOrCreate(
                ['slug' => $sample['slug']],
                [
                    'user_id' => $owner->id,
                    'is_sample' => true,
                    'cover_format' => 'tu',
                    'module_code' => $sample['module_code'],
                    'module_title' => $sample['module_title'],
                    'title' => $sample['title'],
                    'academic_year' => '2024/25',
                    // TU cover + front matter fields.
                    'tu_college_name' => 'Sample College',
                    'tu_institute' => 'Tribhuvan University',
                    'tu_department' => 'Department of Computer Science and Information Technology',
                    'tu_campus_address' => 'Kathmandu, Nepal',
                    'tu_report_type' => 'A Project Report',
                    'tu_supervisor_name' => 'Sample Supervisor',
                    'tu_degree' => 'Bachelor of Science in Computer Science and Information Technology',
                    'tu_students' => ['Sample Student'],
                    'tu_roll_number' => '00000000',
                    'tu_submitted_to_position' => 'Department of Computer Science and Information Technology',
                ],
            );

            // Refresh sections so re-seeding updates content without duplicates.
            $report->sections()->delete();

            $order = 0;
            foreach ($sample['sections'] as [$sectionTitle, $html]) {
                $report->sections()->create([
                    'placement' => 'body',
                    'order' => $order++,
                    'title' => $sectionTitle,
                    'content' => $html,
                ]);
            }

            // Point the matching landing "samples" card at the public sample
            // page. Match by a distinctive keyword in the card title (robust to
            // admin reordering); fall back to positional order.
            $card = $cards->first(fn (LandingFeature $c) => Str::contains($c->title, $sample['match'], ignoreCase: true))
                ?? $cards->get($i);

            $card?->update(['link' => '/samples/'.$sample['slug']]);
        }
    }

    /**
     * @return list<array{slug: string, title: string, module_code: string, module_title: string, match: string, sections: list<array{0: string, 1: string}>}>
     */
    private function samples(): array
    {
        return [
            $this->report(
                'e-commerce-website-project-report-bca',
                'E-commerce Website — BCA/CSIT Project Report',
                'CSIT-EC', 'Web Technologies',
                'an e-commerce website that lets customers browse a product catalogue, add items to a cart, and check out securely online',
                'a product catalogue, shopping cart, order management, and an admin dashboard for inventory',
                'e-commerce',
            ),
            $this->report(
                'hospital-management-system-documentation',
                'Hospital Management System — Project Documentation',
                'CSIT-HMS', 'System Analysis & Design',
                'a hospital management system that digitises patient records, appointment booking, and billing for a mid-sized hospital',
                'patient registration, doctor scheduling, appointments, pharmacy and billing modules',
                'Hospital',
            ),
            $this->report(
                'online-room-finder-project-report',
                'Online Room Finder — Project Report',
                'CSIT-ORF', 'Web Application Development',
                'an online room finder that connects landlords listing rooms with students and tenants searching by location and budget',
                'room listings with photos, map-based search, filters, and landlord–tenant messaging',
                'Room Finder',
            ),
            $this->report(
                'blood-bank-management-system',
                'Blood Bank Management System — Documentation',
                'CSIT-BBM', 'Database Management Systems',
                'a blood bank management system that tracks donors, blood inventory by group, and hospital requests',
                'donor registration, blood-group inventory, request handling, and stock-level reporting',
                'Blood Bank',
            ),
            $this->report(
                'hostel-management-system-tu',
                'Hostel Management System (TU Format)',
                'CSIT-HOS', 'Software Engineering',
                'a hostel management system for a college hostel that handles room allocation, fee collection, and complaints',
                'student admission, room allocation, fee records, mess management, and a complaint register',
                'Hostel',
            ),
            $this->report(
                'ride-sharing-application-documentation',
                'Ride Sharing Application — Final-Year Documentation',
                'CSIT-RSA', 'Mobile Application Development',
                'a ride sharing application that matches riders with nearby drivers and estimates fares in real time',
                'rider and driver apps, live location tracking, fare estimation, and trip history',
                'Ride Sharing',
            ),
        ];
    }

    /**
     * Build one sample report's metadata + section content.
     *
     * @return array{slug: string, title: string, module_code: string, module_title: string, match: string, sections: list<array{0: string, 1: string}>}
     */
    private function report(string $slug, string $title, string $code, string $module, string $about, string $modules, string $match): array
    {
        return [
            'slug' => $slug,
            'title' => $title,
            'module_code' => $code,
            'module_title' => $module,
            'match' => $match,
            'sections' => [
                ['Introduction', "<p>This report documents the design and implementation of {$about}. The project was undertaken as a final-year requirement and follows a standard software development life cycle, from requirement gathering through to testing and deployment.</p><p>The objectives of the project are to automate manual processes, reduce errors, and provide a clear, easy-to-use interface for its users.</p>"],
                ['Literature Review', '<p>Existing solutions in this domain were reviewed to identify common features and gaps. Most comparable systems address the core workflow but vary in usability and reporting. This project draws on those findings to prioritise a clean interface and reliable data handling.</p>'],
                ['System Design', "<p>The system is built around {$modules}. A relational database stores the core entities, and the application layer enforces validation and business rules. Use-case and entity-relationship diagrams were prepared during analysis to model the interactions and data.</p><h2>Methodology</h2><p>An iterative approach was used: each module was designed, implemented, and tested before integration, allowing issues to be caught early.</p>"],
                ['Testing & Results', '<p>Each module was tested with unit and integration tests, and the complete system was validated against the original requirements. The results confirm that the system performs its intended functions reliably under normal use.</p>'],
                ['Conclusion', "<p>The project successfully delivers {$about}. It meets the stated objectives and provides a foundation for future enhancements such as analytics, notifications, and mobile access.</p>"],
                ['References', "<p>Pressman, R. S. (2014) <em>Software Engineering: A Practitioner's Approach</em>. 8th edn. New York: McGraw-Hill.</p><p>Sommerville, I. (2016) <em>Software Engineering</em>. 10th edn. Harlow: Pearson.</p>"],
            ],
        ];
    }
}
