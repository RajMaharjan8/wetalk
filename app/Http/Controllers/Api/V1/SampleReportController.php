<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SampleReportListResource;
use App\Http\Resources\Api\V1\SampleReportResource;
use App\Models\Reference;
use App\Models\Report;
use App\Support\CitationFormatter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only public API exposing the seeded sample reports so third-party sites
 * (e.g. rastriyaaawaj.com) can list and display example documentation.
 */
class SampleReportController extends Controller
{
    /**
     * List every published sample report (newest first), lightweight shape.
     */
    public function index(): AnonymousResourceCollection
    {
        $samples = Report::query()
            ->where('is_sample', true)
            ->whereNotNull('slug')
            ->latest('updated_at')
            ->get();

        return SampleReportListResource::collection($samples);
    }

    /**
     * Return a single sample report with its visible sections and references,
     * the references pre-formatted in the report's citation style and ordered
     * alphabetically by author — matching how the app renders them.
     */
    public function show(Report $report): SampleReportResource
    {
        abort_unless($report->is_sample, 404);

        $report->load([
            'sections' => fn ($query) => $query->where('hidden', false)->orderBy('order'),
            'references',
        ]);

        $format = $report->reference_format ?: 'london-met';
        $formatter = new CitationFormatter($format, $report->references);

        $references = $report->references
            ->sortBy(fn (Reference $reference) => $reference->sortKey())
            ->values()
            ->each(function (Reference $reference) use ($formatter): void {
                $reference->citation = trim($formatter->bibliographyMarker($reference).$formatter->bibliography($reference));
            });

        $report->setRelation('references', $references);

        return new SampleReportResource($report);
    }
}
