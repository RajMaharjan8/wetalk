<?php

namespace App\Support\Checks;

/**
 * Rule-based checks for the Tribhuvan University / Institute of
 * Engineering project-report format (per the IOE guidelines).
 *
 * The TU spec differs from London Met in a few notable ways and the
 * checker mirrors them:
 *  - Title page must name "TRIBHUVAN UNIVERSITY", the institute and the
 *    campus (e.g. PULCHOWK CAMPUS).
 *  - A separate Approval Page is required ("The undersigned certify…").
 *  - A Copyright page is required.
 *  - Body must be 12pt with 1.5 line spacing — the spec does NOT call
 *    out a separate heading point size.
 *  - References use the format `Surname, A., YYYY, Title, …` (commas
 *    around the year) rather than the `Surname, A. (YYYY) Title.` style.
 *  - In-text citations look like `Surname (YYYY)` or `(Surname, YYYY)`.
 */
class TuFormatChecker implements FormatChecker
{
    public function __construct(
        private ReferenceParser $referenceParser = new ReferenceParser,
        private CitationParser $citationParser = new CitationParser,
    ) {}

    public function name(): string
    {
        return 'Tribhuvan University (IOE)';
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
            // Front-matter / structure
            $this->checkTitlePage($pdf),
            $this->checkApprovalPage($pdf),
            $this->checkCopyrightPage($pdf),
            $this->checkAbstract($pdf),
            $this->checkTableOfContents($pdf),
            $this->checkChapterStructure($pdf),
            $this->checkPageNumbers($pdf),
            $this->checkBodyFontSize($pdf),
            $this->checkMinimumLength($pdf),

            // References / citations
            $this->checkReferencesSection($references),
            $this->checkTuReferenceEntryFormat($references),
            $this->checkAlphabeticalOrder($references),
            $this->checkOnlineReferences($references),
            $this->checkCitationsExist($citations),
            $this->checkCitationsMatchReferences($citations, $references),
            $this->checkOrphanReferences($citations, $references),
        ];
    }

    private function checkTitlePage(ParsedPdf $pdf): CheckResult
    {
        $first = $pdf->firstPagesText(2);

        $hasTribhuvan = (bool) preg_match('/tribhuvan\s+university/i', $first);

        // Any TU institute: Science and Technology, Engineering, Medicine,
        // Forestry, Agriculture and Animal Science, etc. We just want
        // "Institute of <Words>" to be present anywhere on the cover.
        $instituteMatched = preg_match('/institute\s+of\s+([A-Z][A-Za-z &]+?)(?:\s*(?:\n|,|amrit|pulchowk|thapathali|kirtipur|tribhuvan|department|in\s+partial))/i', $first, $instituteMatch);
        $instituteName = $instituteMatched ? trim($instituteMatch[1]) : null;
        $hasInstitute = $instituteMatched === 1;

        // Campus: any "X Campus" word pair (Amrit, Pulchowk, Thapathali,
        // Kirtipur, Patan, etc.).
        $hasCampus = (bool) preg_match('/\b[A-Z][a-z]+\s+campus\b/i', $first);

        // Address: anywhere in Nepal (city + Nepal, or a recognisable
        // Nepali city on its own). Comma is preferred but not required.
        $hasNepalAddress = (bool) preg_match('/\bnepal\b/i', $first)
            || (bool) preg_match('/\b(kathmandu|lalitpur|pokhara|bhaktapur|biratnagar|chitwan|dharan|butwal|hetauda|janakpur|thamel|patan)\b/i', $first);

        // Degree: with or without the "DEGREE OF" prefix.
        // Matches both "DEGREE OF BACHELOR OF ENGINEERING" and
        // "Bachelor of Science in Computer Science and Information Technology".
        $hasDegree = (bool) preg_match('/(?:degree\s+of\s+)?\b(bachelor|master|doctor|ph\.?\s*d)\s+of\s+[A-Za-z]+/i', $first);

        // Department: "Department of <Words>" — & is allowed (CSIT etc.).
        $hasDepartment = (bool) preg_match('/department\s+of\s+[A-Za-z][A-Za-z &]+/i', $first);

        // Report/thesis type — usually appears as a separate line.
        $hasReportType = (bool) preg_match('/\b(project\s+work\s+report|project\s+report|thesis|dissertation)\b/i', $first);

        $missing = [];
        if (! $hasTribhuvan) {
            $missing[] = '"Tribhuvan University"';
        }
        if (! $hasInstitute) {
            $missing[] = 'institute line ("Institute of …")';
        }
        if (! $hasCampus) {
            $missing[] = 'campus name (e.g. "Amrit Campus", "Pulchowk Campus")';
        }
        if (! $hasNepalAddress) {
            $missing[] = 'address (Kathmandu / Lalitpur / etc. — must place the campus in Nepal)';
        }
        if (! $hasDegree) {
            $missing[] = 'degree line (e.g. "Bachelor of Science in …", "Master of …")';
        }
        if (! $hasDepartment) {
            $missing[] = 'department line ("Department of …")';
        }
        if (! $hasReportType) {
            $missing[] = 'report type ("Project Work Report", "Thesis" …)';
        }

        if ($missing === []) {
            $instituteDetail = $instituteName !== null ? "{$instituteName}" : 'institute';

            return CheckResult::pass('Title page', "Title page names Tribhuvan University, Institute of {$instituteDetail}, campus, department, degree and report type.");
        }

        return count($missing) >= 3
            ? CheckResult::fail('Title page', 'Missing: '.implode(', ', $missing).'.')
            : CheckResult::warn('Title page', 'Missing: '.implode(', ', $missing).'.');
    }

    private function checkApprovalPage(ParsedPdf $pdf): CheckResult
    {
        $front = $pdf->firstPagesText(min(6, max(2, $pdf->pageCount())));

        $hasCertify = (bool) preg_match('/undersigned\s+certify/i', $front);
        $hasSupervisor = (bool) preg_match('/supervisor/i', $front);
        $hasExternal = (bool) preg_match('/external\s+examiner/i', $front);
        $hasChair = (bool) preg_match('/committee\s+chairperson|head\s+of\s+department/i', $front);

        if ($hasCertify && $hasSupervisor && ($hasExternal || $hasChair)) {
            return CheckResult::pass('Approval page', 'Approval page found ("undersigned certify" + supervisor + examiner/chair).');
        }

        $missing = [];
        if (! $hasCertify) {
            $missing[] = '"The undersigned certify…"';
        }
        if (! $hasSupervisor) {
            $missing[] = 'Supervisor signature line';
        }
        if (! $hasExternal && ! $hasChair) {
            $missing[] = 'External Examiner or Committee Chairperson signature line';
        }

        return CheckResult::fail('Approval page', 'Approval page incomplete. Missing: '.implode(', ', $missing).'.');
    }

    private function checkCopyrightPage(ParsedPdf $pdf): CheckResult
    {
        $front = $pdf->firstPagesText(min(6, max(2, $pdf->pageCount())));

        $hasCopyright = (bool) preg_match('/\bcopyright\b/i', $front);
        $hasLibraryReference = (bool) preg_match('/library/i', $front)
            && (bool) preg_match('/department\s+of/i', $front);

        if ($hasCopyright && $hasLibraryReference) {
            return CheckResult::pass('Copyright page', 'Copyright statement and library/department reference detected.');
        }

        if ($hasCopyright) {
            return CheckResult::warn('Copyright page', 'A copyright statement was found but it should also reference the library and the Department.');
        }

        return CheckResult::fail('Copyright page', 'No copyright page was detected before the abstract. TU requires the standard library/department copyright text.');
    }

    private function checkAbstract(ParsedPdf $pdf): CheckResult
    {
        $front = $pdf->firstPagesText(min(8, max(1, $pdf->pageCount())));

        if (! preg_match('/(^|\n)\s*abstract\s*\n/i', $front)) {
            return CheckResult::fail('Abstract', 'No Abstract section found in the prefatory pages.');
        }

        // Pull the chunk after "Abstract" until the next heading.
        $segment = preg_split('/(^|\n)\s*abstract\s*\n/i', $front, 2)[1] ?? '';
        $segment = preg_split('/\n\s*(table\s+of\s+contents|acknowledgements?|chapter\s+one|1\.)/i', $segment, 2)[0] ?? $segment;

        $words = str_word_count(trim($segment));

        if ($words === 0) {
            return CheckResult::warn('Abstract', 'Abstract heading found but no content could be read.');
        }

        if ($words <= 150) {
            return CheckResult::pass('Abstract', "Abstract is {$words} words (within TU's 150-word limit).");
        }

        return CheckResult::fail('Abstract', "Abstract is approximately {$words} words. TU requires the abstract to be no longer than 150 words and not exceed one page.");
    }

    private function checkTableOfContents(ParsedPdf $pdf): CheckResult
    {
        $first = $pdf->firstPagesText(min(8, max(1, $pdf->pageCount())));

        if (preg_match('/\btable\s+of\s+contents\b/i', $first) || preg_match('/^\s*contents\s*$/im', $first)) {
            return CheckResult::pass('Table of contents', 'A Table of Contents page is present.');
        }

        return CheckResult::fail('Table of contents', 'No "Table of Contents" page was detected.');
    }

    private function checkChapterStructure(ParsedPdf $pdf): CheckResult
    {
        $text = $pdf->fullText();

        $chapterCount = preg_match_all('/\bCHAPTER\s+(ONE|TWO|THREE|FOUR|FIVE|SIX|\d+)\b/i', $text);
        preg_match_all('/^\s*(\d+)\.\s+[A-Z][A-Za-z]/m', $text, $m);
        $uniqueNumeric = count(array_unique($m[1] ?? []));

        if ($chapterCount >= 3 || $uniqueNumeric >= 3) {
            $detail = $chapterCount >= 3
                ? "{$chapterCount} CHAPTER markers detected."
                : "{$uniqueNumeric} numbered top-level sections detected.";

            return CheckResult::pass('Chapter structure', $detail);
        }

        if ($chapterCount > 0 || $uniqueNumeric > 0) {
            return CheckResult::warn('Chapter structure', 'Some chapter / section markers were found but fewer than three top-level chapters were detected.');
        }

        return CheckResult::fail('Chapter structure', 'No "CHAPTER ONE…" or "1. Introduction" style headings were detected. TU expects a chapter structure.');
    }

    private function checkPageNumbers(ParsedPdf $pdf): CheckResult
    {
        if ($pdf->pageCount() < 4) {
            return CheckResult::warn('Page numbers', 'Document is too short to evaluate page numbering.');
        }

        $numbered = 0;
        foreach ($pdf->pages as $index => $page) {
            if ($index === 0) {
                continue; // title page is not numbered
            }
            if (preg_match('/(^|\s)\d{1,4}\s*$/', trim($page))) {
                $numbered++;
            }
        }

        $checked = $pdf->pageCount() - 1;
        $ratio = $checked > 0 ? $numbered / $checked : 0;

        if ($ratio >= 0.6) {
            return CheckResult::pass('Page numbers', 'Most pages appear to carry a page number (Arabic numerals, centered at the bottom per TU spec).');
        }

        if ($ratio >= 0.2) {
            return CheckResult::warn('Page numbers', 'Page numbers are inconsistent — only some pages appear numbered.');
        }

        return CheckResult::fail('Page numbers', 'No page numbers detected. TU requires Arabic numerals centered 1 inch from the bottom on every page except the title page.');
    }

    private function checkBodyFontSize(ParsedPdf $pdf): CheckResult
    {
        if (! $pdf->hasFontSizeData()) {
            return CheckResult::warn('Body font size (12pt)', 'Font sizes could not be read from this PDF.');
        }

        $bins = [];
        foreach ($pdf->fragments as $f) {
            if ($f['page'] < 2) {
                continue;
            }
            $key = (string) round($f['font_size'], 1);
            $bins[$key] = ($bins[$key] ?? 0) + max(1, str_word_count($f['text']));
        }

        if ($bins === []) {
            return CheckResult::warn('Body font size (12pt)', 'Could not determine the body font size from this PDF.');
        }

        arsort($bins);
        $dominant = (float) array_key_first($bins);

        // Headless-Chromium / Paged.js PDFs encode the size outside the text
        // matrix, so the reading collapses to ~1pt. Treat that as unmeasurable
        // (warn) rather than a false failure.
        if ($dominant < 4.0) {
            return CheckResult::warn('Body font size (12pt)', 'Font size could not be measured reliably from this PDF. Ensure the body text is 12pt.');
        }

        if (abs($dominant - 12.0) <= 0.6) {
            return CheckResult::pass('Body font size (12pt)', "Body text ≈ {$dominant}pt — matches the TU 12pt requirement.");
        }

        return CheckResult::fail('Body font size (12pt)', "Body text measures ≈ {$dominant}pt. TU requires 12pt throughout.");
    }

    private function checkMinimumLength(ParsedPdf $pdf): CheckResult
    {
        $words = str_word_count($pdf->fullText());

        if ($words >= 3000) {
            return CheckResult::pass('Length', "Approximate word count: {$words}.");
        }

        return CheckResult::warn('Length', "Approximate word count: {$words}. TU project reports are typically substantially longer.");
    }

    /**
     * @param  array{text: string, entries: list<array<string, mixed>>}  $references
     */
    private function checkReferencesSection(array $references): CheckResult
    {
        if ($references['text'] === '') {
            return CheckResult::fail('References section', 'No "References" section was found near the end of the document.');
        }

        $count = count($references['entries']);

        if ($count === 0) {
            return CheckResult::fail('References section', '"References" heading found but no entries were detected.');
        }

        return CheckResult::pass('References section', "Found {$count} reference entries.");
    }

    /**
     * TU style: "Surname, Initial., YYYY, Title, Publisher, …"
     * — commas around the year, NOT parentheses.
     *
     * @param  array{text: string, entries: list<array<string, mixed>>}  $references
     */
    private function checkTuReferenceEntryFormat(array $references): CheckResult
    {
        if ($references['entries'] === []) {
            return CheckResult::fail('TU reference format', 'No reference entries to check.');
        }

        $bad = [];
        foreach ($references['entries'] as $entry) {
            $raw = (string) $entry['raw'];
            $issues = [];

            if ($entry['surname'] === null) {
                $issues[] = 'no Surname, Initial.';
            }

            $tuYear = (bool) preg_match('/,\s*\d{4}[a-z]?\s*[,.]/', $raw);
            $harvardYear = (bool) preg_match('/\(\d{4}[a-z]?\)/', $raw);

            if (! $tuYear && $harvardYear) {
                $issues[] = 'year is "(YYYY)" — TU wants ", YYYY," with commas';
            } elseif (! $tuYear && ! $harvardYear) {
                $issues[] = 'no year detected';
            }

            if (str_word_count($raw) < 5) {
                $issues[] = 'too short for a full reference';
            }

            if ($issues !== []) {
                $preview = mb_strimwidth($raw, 0, 70, '…');
                $bad[] = "\"{$preview}\" — ".implode(', ', $issues);
            }
        }

        $total = count($references['entries']);
        $badCount = count($bad);

        if ($badCount === 0) {
            return CheckResult::pass('TU reference format', "All {$total} entries match the TU pattern (Surname, Initial., YYYY, …).");
        }

        $detail = "{$badCount} of {$total} entries don't match the TU pattern. First few: ".implode(' | ', array_slice($bad, 0, 3));

        return $badCount >= max(1, (int) ceil($total / 2))
            ? CheckResult::fail('TU reference format', $detail)
            : CheckResult::warn('TU reference format', $detail);
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
            return CheckResult::pass('Alphabetical order', 'Reference entries are alphabetised by surname.');
        }

        return CheckResult::fail('Alphabetical order', 'References should be listed A→Z by surname.');
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

        return CheckResult::pass('Online references', count($online).' online reference(s) include a URL.');
    }

    /**
     * @param  list<array{surname: string, year: string, raw: string}>  $citations
     */
    private function checkCitationsExist(array $citations): CheckResult
    {
        if ($citations === []) {
            return CheckResult::fail('In-text citations', 'No in-text citations like (Smith, 2021) or Smith (2021) were found. TU body text must cite sources.');
        }

        return CheckResult::pass('In-text citations', count($citations).' citation occurrences detected.');
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
        $seen = [];
        foreach ($citations as $c) {
            $key = strtolower($c['surname']).'|'.$c['year'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if (! in_array(strtolower($c['surname']), $refSurnames, true)) {
                $missing[] = $c['raw'];
            }
        }

        if ($missing === []) {
            return CheckResult::pass('Citations match references', 'Every cited author appears in the reference list.');
        }

        return CheckResult::fail('Citations match references', count($missing).' cited author(s) have no matching entry in the reference list: '.implode(', ', array_slice($missing, 0, 5)));
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

        return CheckResult::warn('Orphan references', count($orphans).' reference(s) are never cited in the body: '.implode(', ', array_slice($orphans, 0, 5)));
    }
}
