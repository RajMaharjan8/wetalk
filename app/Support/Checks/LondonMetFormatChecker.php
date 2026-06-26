<?php

namespace App\Support\Checks;

/**
 * Rule-based checks for the London Met assignment report format.
 *
 * Checks the things that survive PDF text extraction. Font family,
 * italics, exact margins and line-spacing are intentionally NOT checked
 * because they're not reliable from extracted text alone.
 */
class LondonMetFormatChecker implements FormatChecker
{
    public function __construct(
        private ReferenceParser $referenceParser = new ReferenceParser,
        private CitationParser $citationParser = new CitationParser,
    ) {}

    public function name(): string
    {
        return 'London Metropolitan University';
    }

    /**
     * @return list<CheckResult>
     */
    public function check(ParsedPdf $pdf): array
    {
        $fullText = $pdf->fullText();
        $references = $this->referenceParser->parse($fullText);
        $bodyText = $references['text'] === '' ? $fullText : str_replace($references['text'], '', $fullText);
        $citations = $this->citationParser->parse($bodyText);

        return [
            // Structure
            $this->checkCoverPage($pdf),
            $this->checkModuleCode($pdf),
            $this->checkStudentIdentity($pdf),
            $this->checkTableOfContents($pdf),
            $this->checkAbstract($pdf),
            $this->checkNumberedSections($pdf),
            $this->checkPageNumbers($pdf),
            $this->checkMinimumLength($pdf),
            $this->checkFontSizes($pdf),

            // References / citations
            $this->checkReferencesSection($references),
            $this->checkReferenceEntryFormat($references),
            $this->checkHangingIndent($references),
            $this->checkAlphabeticalOrder($references),
            $this->checkOnlineReferences($references),
            $this->checkCitationsExist($citations),
            $this->checkCitationsMatchReferences($citations, $references),
            $this->checkOrphanReferences($citations, $references),
        ];
    }

    private function checkCoverPage(ParsedPdf $pdf): CheckResult
    {
        $first = $pdf->firstPagesText(2);

        $hasLondonMet = (bool) preg_match('/london\s*metropolitan\s*university/i', $first);
        $hasIslington = (bool) preg_match('/islington\s*college/i', $first);

        if ($hasLondonMet && $hasIslington) {
            return CheckResult::pass('Cover page', 'London Metropolitan University and Islington College both appear on the cover.');
        }

        if ($hasLondonMet || $hasIslington) {
            return CheckResult::warn('Cover page', 'Cover page should name both Islington College and London Metropolitan University.');
        }

        return CheckResult::fail('Cover page', 'Could not find "London Metropolitan University" or "Islington College" on the first pages.');
    }

    private function checkModuleCode(ParsedPdf $pdf): CheckResult
    {
        $first = $pdf->firstPagesText(2);

        if (preg_match('/\b[A-Z]{2,4}\d{3,5}[A-Z]{0,3}\b/', $first, $m)) {
            return CheckResult::pass('Module code', "Found module code: {$m[0]}");
        }

        return CheckResult::fail('Module code', 'No module code (e.g. MN7001NI) was found on the cover.');
    }

    private function checkStudentIdentity(ParsedPdf $pdf): CheckResult
    {
        $first = $pdf->firstPagesText(2);

        $hasLondonId = (bool) preg_match('/\b\d{8}\b/', $first);
        $hasName = (bool) preg_match('/(student\s*name|submitted\s*by)/i', $first);

        if ($hasLondonId && $hasName) {
            return CheckResult::pass('Student identity', 'London Met ID and student name both present on the cover.');
        }

        if ($hasLondonId || $hasName) {
            return CheckResult::warn('Student identity', 'Cover should include both your name and your 8-digit London Met ID.');
        }

        return CheckResult::fail('Student identity', 'Could not find a student name or 8-digit London Met ID on the cover.');
    }

    private function checkTableOfContents(ParsedPdf $pdf): CheckResult
    {
        $first = $pdf->firstPagesText(min(6, max(1, $pdf->pageCount())));

        if (preg_match('/\btable\s+of\s+contents\b/i', $first) || preg_match('/^\s*contents\s*$/im', $first)) {
            return CheckResult::pass('Table of contents', 'A contents page is present.');
        }

        return CheckResult::fail('Table of contents', 'No "Table of Contents" page was detected near the start.');
    }

    private function checkAbstract(ParsedPdf $pdf): CheckResult
    {
        $first = $pdf->firstPagesText(min(8, max(1, $pdf->pageCount())));

        if (preg_match('/^\s*abstract\s*$/im', $first) || preg_match('/\babstract\b\s*\n/i', $first)) {
            return CheckResult::pass('Abstract', 'An Abstract section was detected.');
        }

        return CheckResult::warn('Abstract', 'No Abstract page found. London Met reports usually include an abstract before the contents.');
    }

    private function checkNumberedSections(ParsedPdf $pdf): CheckResult
    {
        $text = $pdf->fullText();

        preg_match_all('/^\s*(\d+)\.\s+[A-Z][A-Za-z]/m', $text, $matches);
        $sectionCount = count(array_unique($matches[1] ?? []));

        if ($sectionCount >= 3) {
            return CheckResult::pass('Numbered sections', "Found {$sectionCount} numbered top-level sections.");
        }

        if ($sectionCount > 0) {
            return CheckResult::warn('Numbered sections', "Only {$sectionCount} numbered section(s) detected. A report normally has at least 3 (Introduction, Main body, Conclusion).");
        }

        return CheckResult::fail('Numbered sections', 'No numbered sections like "1. Introduction" were found.');
    }

    private function checkPageNumbers(ParsedPdf $pdf): CheckResult
    {
        if ($pdf->pageCount() < 4) {
            return CheckResult::warn('Page numbers', 'Document is too short to evaluate page numbering.');
        }

        $numbered = 0;
        foreach ($pdf->pages as $index => $page) {
            if ($index < 2) {
                continue;
            }
            if (preg_match('/(^|\s)\d{1,3}\s*$/', trim($page))) {
                $numbered++;
            }
        }

        $checked = $pdf->pageCount() - 2;
        $ratio = $checked > 0 ? $numbered / $checked : 0;

        if ($ratio >= 0.6) {
            return CheckResult::pass('Page numbers', 'Most pages appear to carry a page number.');
        }

        if ($ratio >= 0.2) {
            return CheckResult::warn('Page numbers', 'Page numbers are inconsistent — only some pages appear to be numbered.');
        }

        return CheckResult::fail('Page numbers', 'No page numbers were detected. London Met reports need page numbers (Roman for front matter, Arabic from the body).');
    }

    private function checkMinimumLength(ParsedPdf $pdf): CheckResult
    {
        $words = str_word_count($pdf->fullText());

        if ($words >= 1500) {
            return CheckResult::pass('Length', "Approximate word count: {$words}.");
        }

        return CheckResult::warn('Length', "Approximate word count: {$words}. London Met assignments usually require more (often 2,000–3,000 words).");
    }

    /**
     * Heading 1 should render at ~14pt and body text at ~12pt. We read
     * font sizes from each text fragment's PDF transformation matrix.
     * Some PDFs hide this data — in that case we warn-and-skip instead
     * of pretending to know.
     */
    private function checkFontSizes(ParsedPdf $pdf): CheckResult
    {
        if (! $pdf->hasFontSizeData()) {
            return CheckResult::warn('Font sizes (Heading 14pt / body 12pt)', 'Font sizes could not be read from this PDF — likely flattened to images or using non-standard text encoding.');
        }

        $bodySize = $this->dominantBodyFontSize($pdf);
        $headingSizes = $this->headingFontSizes($pdf);

        if ($bodySize === null) {
            return CheckResult::warn('Font sizes (Heading 14pt / body 12pt)', 'Could not determine the body font size from this PDF.');
        }

        // Some PDF generators (e.g. headless-Chromium / Paged.js output) encode
        // the size at the font-selection operator rather than the text matrix,
        // so the measured value collapses to ~1pt. That's "unmeasurable", not
        // "wrong" — warn instead of failing on an obviously bogus reading.
        if ($bodySize < 4.0) {
            return CheckResult::warn('Font sizes (Heading 14pt / body 12pt)', 'Font sizes could not be measured reliably from this PDF (the text matrix does not carry point sizes). Check the document uses 12pt body / 14pt headings.');
        }

        $bodyOk = abs($bodySize - 12.0) <= 0.6;
        $headingOk = $headingSizes !== [] && $this->averageInRange($headingSizes, 14.0, 0.8);

        $bodyMsg = "Body text ≈ {$bodySize}pt";
        $headingMsg = $headingSizes === []
            ? 'no Heading 1 found to measure'
            : 'Heading 1 ≈ '.round(array_sum($headingSizes) / count($headingSizes), 1).'pt';

        if ($bodyOk && $headingOk) {
            return CheckResult::pass('Font sizes (Heading 14pt / body 12pt)', "{$bodyMsg}; {$headingMsg}.");
        }

        $issues = [];
        if (! $bodyOk) {
            $issues[] = "body should be 12pt but measured ≈ {$bodySize}pt";
        }
        if (! $headingOk && $headingSizes !== []) {
            $avg = round(array_sum($headingSizes) / count($headingSizes), 1);
            $issues[] = "Heading 1 should be 14pt but measured ≈ {$avg}pt";
        } elseif ($headingSizes === []) {
            $issues[] = 'no Heading 1 found to measure';
        }

        return CheckResult::fail('Font sizes (Heading 14pt / body 12pt)', implode('; ', $issues).'.');
    }

    /**
     * The most-used font size across body pages (skipping the cover and
     * page 2 so the cover-page huge fonts don't dominate the histogram).
     */
    private function dominantBodyFontSize(ParsedPdf $pdf): ?float
    {
        $bins = [];
        foreach ($pdf->fragments as $f) {
            if ($f['page'] < 2) {
                continue;
            }
            $key = (string) round($f['font_size'], 1);
            $bins[$key] = ($bins[$key] ?? 0) + max(1, str_word_count($f['text']));
        }

        if ($bins === []) {
            return null;
        }

        arsort($bins);
        $top = array_key_first($bins);

        return (float) $top;
    }

    /**
     * Sizes of fragments that look like a level-1 section heading
     * ("1. Introduction", "2. Background", …) — used to estimate the
     * actual Heading 1 font size.
     *
     * @return list<float>
     */
    private function headingFontSizes(ParsedPdf $pdf): array
    {
        $sizes = [];
        foreach ($pdf->fragments as $f) {
            if (preg_match('/^\s*\d+\.\s+[A-Z][A-Za-z]/', $f['text'])) {
                $sizes[] = $f['font_size'];
            }
        }

        return $sizes;
    }

    /**
     * @param  list<float>  $sizes
     */
    private function averageInRange(array $sizes, float $target, float $tolerance): bool
    {
        if ($sizes === []) {
            return false;
        }
        $avg = array_sum($sizes) / count($sizes);

        return abs($avg - $target) <= $tolerance;
    }

    /**
     * @param  array{text: string, entries: list<array<string, mixed>>}  $references
     */
    private function checkReferencesSection(array $references): CheckResult
    {
        if ($references['text'] === '') {
            return CheckResult::fail('References section', 'No "References" or "Bibliography" section was found near the end of the document.');
        }

        $count = count($references['entries']);

        if ($count === 0) {
            return CheckResult::fail('References section', '"References" heading found but no entries were detected.');
        }

        return CheckResult::pass('References section', "Found {$count} reference entries.");
    }

    /**
     * @param  array{text: string, entries: list<array<string, mixed>>}  $references
     */
    private function checkReferenceEntryFormat(array $references): CheckResult
    {
        if ($references['entries'] === []) {
            return CheckResult::fail('Reference entry format', 'No reference entries to check.');
        }

        $bad = [];
        foreach ($references['entries'] as $entry) {
            $issues = [];
            if ($entry['surname'] === null) {
                $issues[] = 'no Surname, Initial';
            }
            if (! $entry['has_year']) {
                $issues[] = 'no (YYYY)';
            }
            if (str_word_count($entry['raw']) < 5) {
                $issues[] = 'too short to be a full reference';
            }
            if ($issues !== []) {
                $preview = mb_strimwidth($entry['raw'], 0, 70, '…');
                $bad[] = "\"{$preview}\" — ".implode(', ', $issues);
            }
        }

        $total = count($references['entries']);
        $badCount = count($bad);

        if ($badCount === 0) {
            return CheckResult::pass('Reference entry format', "All {$total} entries follow the Surname, Initial. (YYYY) Title pattern.");
        }

        $detail = "{$badCount} of {$total} entries don't match Harvard format. First few: ".implode(' | ', array_slice($bad, 0, 3));

        return $badCount >= max(1, (int) ceil($total / 2))
            ? CheckResult::fail('Reference entry format', $detail)
            : CheckResult::warn('Reference entry format', $detail);
    }

    /**
     * @param  array{text: string, entries: list<array<string, mixed>>}  $references
     */
    private function checkHangingIndent(array $references): CheckResult
    {
        $multiLine = array_values(array_filter(
            $references['entries'],
            fn (array $e) => ($e['continuation_lines'] ?? 0) > 0,
        ));

        if ($multiLine === []) {
            return CheckResult::warn('Hanging indent', 'No multi-line reference entries detected, so hanging-indent style could not be evaluated. Make sure long references wrap with a hanging indent.');
        }

        $withIndent = 0;
        foreach ($multiLine as $entry) {
            if ($entry['hanging_indent_lines'] >= max(1, (int) floor($entry['continuation_lines'] / 2))) {
                $withIndent++;
            }
        }

        $ratio = $withIndent / count($multiLine);

        if ($ratio >= 0.75) {
            return CheckResult::pass('Hanging indent', "Most multi-line references appear to use a hanging indent ({$withIndent} of ".count($multiLine).').');
        }

        $detail = 'In Harvard style, the second and later lines of each reference should be indented (hanging indent). Detected indent on '
            ."{$withIndent} of ".count($multiLine).' multi-line entries.';

        return $ratio >= 0.25
            ? CheckResult::warn('Hanging indent', $detail)
            : CheckResult::fail('Hanging indent', $detail);
    }

    /**
     * @param  array{text: string, entries: list<array<string, mixed>>}  $references
     */
    private function checkAlphabeticalOrder(array $references): CheckResult
    {
        $surnames = array_values(array_filter(array_map(fn (array $e) => $e['surname'], $references['entries'])));

        if (count($surnames) < 2) {
            return CheckResult::warn('Alphabetical order', 'Not enough reference entries to check alphabetical order.');
        }

        $sorted = $surnames;
        sort($sorted, SORT_STRING | SORT_FLAG_CASE);

        if ($sorted === $surnames) {
            return CheckResult::pass('Alphabetical order', 'Reference entries are in alphabetical order by surname.');
        }

        $outOfPlace = [];
        for ($i = 0; $i < count($surnames); $i++) {
            if (($surnames[$i] ?? null) !== ($sorted[$i] ?? null)) {
                $outOfPlace[] = $surnames[$i];
            }
        }

        $sample = implode(', ', array_slice($outOfPlace, 0, 4));

        return CheckResult::fail('Alphabetical order', "References should be listed A→Z by surname. Out of place: {$sample}.");
    }

    /**
     * @param  array{text: string, entries: list<array<string, mixed>>}  $references
     */
    private function checkOnlineReferences(array $references): CheckResult
    {
        $online = array_values(array_filter($references['entries'], fn (array $e) => $e['has_url']));

        if ($online === []) {
            return CheckResult::pass('Online references', 'No URL-based references to evaluate.');
        }

        $missing = array_values(array_filter($online, fn (array $e) => ! $e['has_access_date']));

        if ($missing === []) {
            return CheckResult::pass('Online references', 'All web references include an "Available at:" URL and "[Accessed …]" date.');
        }

        $sample = mb_strimwidth($missing[0]['raw'], 0, 80, '…');

        return CheckResult::fail('Online references', count($missing).' of '.count($online).' online references are missing an "[Accessed DD Month YYYY]" date. First: "'.$sample.'"');
    }

    /**
     * @param  list<array{surname: string, year: string, raw: string}>  $citations
     */
    private function checkCitationsExist(array $citations): CheckResult
    {
        if ($citations === []) {
            return CheckResult::fail('In-text citations', 'No in-text citations like (Smith, 2021) or Smith (2021) were found. A report must cite sources where they\'re used.');
        }

        $unique = $this->uniqueAuthorYears($citations);

        return CheckResult::pass('In-text citations', count($citations).' citation occurrences ('.count($unique).' unique author-year combinations) detected in the body.');
    }

    /**
     * @param  list<array{surname: string, year: string, raw: string}>  $citations
     * @param  array{text: string, entries: list<array<string, mixed>>}  $references
     */
    private function checkCitationsMatchReferences(array $citations, array $references): CheckResult
    {
        if ($citations === []) {
            return CheckResult::warn('Citations match references', 'No citations to verify against the reference list.');
        }

        $refSurnames = array_values(array_filter(array_map(fn (array $e) => strtolower((string) $e['surname']), $references['entries'])));

        $missing = [];
        foreach ($this->uniqueAuthorYears($citations) as $key => $raw) {
            [$surname] = explode('|', $key);
            if (! in_array(strtolower($surname), $refSurnames, true)) {
                $missing[] = $raw;
            }
        }

        if ($missing === []) {
            return CheckResult::pass('Citations match references', 'Every cited author appears in the reference list.');
        }

        $sample = implode(', ', array_slice($missing, 0, 5));

        return CheckResult::fail('Citations match references', count($missing).' cited author(s) have no matching entry in the reference list: '.$sample);
    }

    /**
     * @param  list<array{surname: string, year: string, raw: string}>  $citations
     * @param  array{text: string, entries: list<array<string, mixed>>}  $references
     */
    private function checkOrphanReferences(array $citations, array $references): CheckResult
    {
        if ($references['entries'] === []) {
            return CheckResult::warn('Orphan references', 'No references to evaluate.');
        }

        $citedSurnames = array_unique(array_map(fn (array $c) => strtolower($c['surname']), $citations));

        $orphans = [];
        foreach ($references['entries'] as $entry) {
            if ($entry['surname'] === null) {
                continue;
            }
            if (! in_array(strtolower((string) $entry['surname']), $citedSurnames, true)) {
                $orphans[] = $entry['surname'];
            }
        }

        if ($orphans === []) {
            return CheckResult::pass('Orphan references', 'Every reference is cited at least once in the body.');
        }

        $sample = implode(', ', array_slice($orphans, 0, 5));

        return CheckResult::warn('Orphan references', count($orphans).' reference(s) are never cited in the body: '.$sample);
    }

    /**
     * Deduplicate citations by surname+year, keeping the first raw occurrence.
     *
     * @param  list<array{surname: string, year: string, raw: string}>  $citations
     * @return array<string, string> key = "surname|year", value = raw text
     */
    private function uniqueAuthorYears(array $citations): array
    {
        $unique = [];
        foreach ($citations as $c) {
            $key = $c['surname'].'|'.$c['year'];
            $unique[$key] ??= $c['raw'];
        }

        return $unique;
    }
}
