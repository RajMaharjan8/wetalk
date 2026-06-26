<?php

namespace App\Support;

use App\Models\Reference;
use App\Models\Report;
use App\Models\Section;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Illuminate\Database\Eloquent\Collection;

/**
 * Compiles a report into the data a paginated document needs:
 *
 *  - section HTML with section-prefixed heading numbers baked in (1, 2.1, 2.4.1 …)
 *  - a flat Table of Contents with anchor ids for every heading
 *  - numbered figures and tables with anchor ids for the Table of Figures / Tables
 *
 * Anchor ids let Paged.js resolve real page numbers via `target-counter()`.
 */
class ReportCompiler
{
    /** @var list<array{number: string, marker: string, title: string, id: string, html: string}> */
    protected array $sections = [];

    /** @var list<array{title: string, id: string, html: string}> */
    protected array $frontMatter = [];

    /** @var list<array{title: string, id: string, html: string}> Unnumbered back matter (References, Appendix) rendered after the body. */
    protected array $backMatter = [];

    /** @var list<array{level: int, number: string, marker: string, label: string, id: string}> */
    protected array $contents = [];

    /** @var list<array{level: int, number: string, marker: string, label: string, id: string}> ToC entries for back matter, appended after the body. */
    protected array $backContents = [];

    /** @var list<array{number: int, label: string, caption: string, id: string, section: string}> */
    protected array $figures = [];

    /** @var list<array{number: int, label: string, caption: string, id: string, section: string}> */
    protected array $tables = [];

    protected int $figureCount = 0;

    protected int $tableCount = 0;

    protected CitationFormatter $citationFormatter;

    /** @var Collection<int, Reference> */
    protected Collection $references;

    /** @var array<int, true> Reference ids that appear in the body text. */
    protected array $usedReferenceIds = [];

    public function __construct(protected Report $report)
    {
        $this->references = $report->references()->get();
        $this->citationFormatter = new CitationFormatter(
            $report->citationFormat(),
            $this->references,
        );
    }

    public static function for(Report $report): self
    {
        return (new self($report))->compile();
    }

    public function compile(): self
    {
        $sectionIndex = 0;

        // Process back matter (References, Appendix) LAST regardless of each
        // section's stored order, so every body citation has been counted before
        // the references-list placeholder renders. Relative order within each
        // group (sections are already sorted by `order`) is preserved.
        $ordered = $this->report->sections
            ->sortBy(fn (Section $s) => $s->placement === 'back' ? 1 : 0)
            ->values();

        foreach ($ordered as $section) {
            // Hidden sections keep their content but are excluded from the
            // compiled report — and skip the numbering so visible sections
            // renumber 1, 2, 3 … without gaps.
            if ($section->hidden) {
                continue;
            }

            // Custom front-matter pages render before the contents — they are
            // not numbered and do not appear in the Table of Contents.
            if ($section->placement === 'front') {
                $this->frontMatter[] = [
                    'title' => (string) $section->title,
                    'id' => 'front-'.$section->id,
                    'html' => $this->processFrontPage($section),
                ];

                continue;
            }

            // Back matter (References, Appendix …) renders after the body. It is
            // NOT numbered as a chapter — the heading is just the plain title —
            // but it does appear in the Table of Contents (without a number).
            if ($section->placement === 'back') {
                $backAnchor = 'back-'.$section->id;

                // Collected separately so back matter always lists after the
                // numbered body sections in the Table of Contents.
                $this->backContents[] = [
                    'level' => 1,
                    'number' => '',
                    'marker' => '',
                    'label' => (string) $section->title,
                    'id' => $backAnchor,
                ];

                $this->backMatter[] = [
                    'title' => (string) $section->title,
                    'id' => $backAnchor,
                    'html' => $this->processSection($section, 0),
                ];

                continue;
            }

            $sectionIndex++;
            $sectionAnchor = 'sec-'.$section->id;
            $marker = $this->sectionMarker($sectionIndex);

            $this->contents[] = [
                'level' => 1,
                'number' => (string) $sectionIndex,
                'marker' => $marker,
                'label' => (string) $section->title,
                'id' => $sectionAnchor,
            ];

            $this->sections[] = [
                'number' => (string) $sectionIndex,
                'marker' => $marker,
                'title' => (string) $section->title,
                'id' => $sectionAnchor,
                'html' => $this->processSection($section, $sectionIndex),
            ];
        }

        // Back matter is listed after every numbered body section in the ToC.
        $this->contents = [...$this->contents, ...$this->backContents];

        return $this;
    }

    /**
     * The prefix shown before a section title — plain "1." by default, or a
     * word-based label like "Chapter 1:" when the report sets section_label.
     */
    protected function sectionMarker(int $sectionIndex): string
    {
        $label = trim((string) $this->report->section_label);

        return $label === '' ? $sectionIndex.'.' : $label.' '.$sectionIndex.':';
    }

    /** @return list<array{number: string, marker: string, title: string, id: string, html: string}> */
    public function sections(): array
    {
        return $this->sections;
    }

    /** @return list<array{title: string, id: string, html: string}> */
    public function frontMatter(): array
    {
        return $this->frontMatter;
    }

    public function hasFrontMatter(): bool
    {
        return $this->frontMatter !== [];
    }

    /** @return list<array{title: string, id: string, html: string}> */
    public function backMatter(): array
    {
        return $this->backMatter;
    }

    public function hasBackMatter(): bool
    {
        return $this->backMatter !== [];
    }

    /** @return list<array{level: int, number: string, marker: string, label: string, id: string}> */
    public function contents(): array
    {
        return $this->contents;
    }

    /** @return list<array{number: int, label: string, caption: string, id: string, section: string}> */
    public function figures(): array
    {
        return $this->figures;
    }

    /** @return list<array{number: int, label: string, caption: string, id: string, section: string}> */
    public function tables(): array
    {
        return $this->tables;
    }

    public function hasFigures(): bool
    {
        return $this->figures !== [];
    }

    public function hasTables(): bool
    {
        return $this->tables !== [];
    }

    /**
     * Prepare a front-matter page's HTML: blank paragraphs are stripped, but
     * headings, figures and tables are left unnumbered and out of the report's
     * counters since front pages sit before the contents.
     */
    protected function processFrontPage(Section $section): string
    {
        $html = SectionContent::toHtml($section->content);

        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="report-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $document->getElementById('report-root');

        if (! $root instanceof DOMElement) {
            return $html;
        }

        $this->removeEmptyParagraphs($document);
        $this->renderCitations($document);
        $this->renderReferencesPlaceholder($document);

        return $this->innerHtml($root);
    }

    protected function processSection(Section $section, int $sectionIndex): string
    {
        $html = SectionContent::toHtml($section->content);

        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="report-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $document->getElementById('report-root');

        if (! $root instanceof DOMElement) {
            return $html;
        }

        $this->removeEmptyParagraphs($document);
        $this->numberHeadings($document, $section, $sectionIndex);
        $this->numberFigures($document, (string) $section->title);
        $this->numberTables($document, (string) $section->title);
        $this->renderCitations($document);
        $this->renderReferencesPlaceholder($document);

        return $this->innerHtml($root);
    }

    /**
     * Drop blank paragraphs (empty lines, stray <p><br></p>) so the printed
     * report has no unintended vertical gaps.
     */
    protected function removeEmptyParagraphs(DOMDocument $document): void
    {
        foreach (iterator_to_array($document->getElementsByTagName('p')) as $paragraph) {
            if (! $paragraph instanceof DOMElement) {
                continue;
            }

            if ($paragraph->getElementsByTagName('img')->length > 0) {
                continue;
            }

            $text = str_replace("\u{00A0}", ' ', $paragraph->textContent);

            if (trim($text) === '') {
                $paragraph->parentNode?->removeChild($paragraph);
            }
        }
    }

    /**
     * Walk every heading in document order, assigning section-prefixed numbers
     * and an anchor id, and record it in the Table of Contents.
     *
     * The section title is heading level 1, so editor headings nest beneath it:
     * an <h2> becomes section.major (e.g. 2.1) and an <h3> becomes
     * section.major.minor (e.g. 2.1.3). A legacy <h1> is treated as an <h2>.
     */
    protected function numberHeadings(DOMDocument $document, Section $section, int $sectionIndex): void
    {
        $xpath = new DOMXPath($document);
        $headings = $xpath->query('//h1|//h2|//h3');

        if (! $headings instanceof DOMNodeList) {
            return;
        }

        $major = 0;
        $minor = 0;
        $headingIndex = 0;

        foreach ($headings as $heading) {
            if (! $heading instanceof DOMElement) {
                continue;
            }

            if ($heading->tagName === 'h3') {
                $minor++;
                $number = $sectionIndex.'.'.$major.'.'.$minor;
                $level = 3;
            } else {
                $major++;
                $minor = 0;
                $number = $sectionIndex.'.'.$major;
                $level = 2;
            }

            $headingIndex++;
            $anchor = 'h-'.$section->id.'-'.$headingIndex;
            $heading->setAttribute('id', $anchor);

            $this->contents[] = [
                'level' => $level,
                'number' => $number,
                'marker' => $number.'.',
                'label' => trim((string) preg_replace('/\s+/', ' ', $heading->textContent)),
                'id' => $anchor,
            ];

            $heading->insertBefore($document->createTextNode($number.'.  '), $heading->firstChild);
        }
    }

    protected function numberFigures(DOMDocument $document, string $sectionTitle): void
    {
        foreach ($this->collect($document, 'figure') as $figure) {
            if ($figure->getElementsByTagName('img')->length === 0) {
                continue;
            }

            $this->figureCount++;
            $anchor = 'fig-'.$this->figureCount;
            $figure->setAttribute('id', $anchor);

            $caption = $this->stripLabelPrefix($this->captionText($figure->getElementsByTagName('figcaption')), 'Figure');
            $label = $caption === '' ? "Figure {$this->figureCount}" : "Figure {$this->figureCount}: {$caption}";

            $this->figures[] = [
                'number' => $this->figureCount,
                'label' => $label,
                'caption' => $caption,
                'id' => $anchor,
                'section' => $sectionTitle,
            ];

            $this->writeCaption($figure, 'figcaption', $document, $label);
        }
    }

    protected function numberTables(DOMDocument $document, string $sectionTitle): void
    {
        foreach ($this->collect($document, 'table') as $table) {
            $this->tableCount++;
            $anchor = 'tbl-'.$this->tableCount;

            // CKEditor wraps tables in <figure class="table">; anchor the block.
            $wrapper = $table->parentNode;
            $isFigure = $wrapper instanceof DOMElement && $wrapper->tagName === 'figure';
            ($isFigure ? $wrapper : $table)->setAttribute('id', $anchor);

            // The caption may sit in <table><caption> or <figure><figcaption>.
            $captionEl = $table->getElementsByTagName('caption')->item(0);

            if (! $captionEl instanceof DOMElement && $isFigure) {
                foreach ($wrapper->childNodes as $child) {
                    if ($child instanceof DOMElement && $child->tagName === 'figcaption') {
                        $captionEl = $child;
                        break;
                    }
                }
            }

            $caption = $this->stripLabelPrefix($captionEl instanceof DOMElement ? trim($captionEl->textContent) : '', 'Table');
            $label = $caption === '' ? "Table {$this->tableCount}" : "Table {$this->tableCount}: {$caption}";

            $this->tables[] = [
                'number' => $this->tableCount,
                'label' => $label,
                'caption' => $caption,
                'id' => $anchor,
                'section' => $sectionTitle,
            ];

            if ($captionEl instanceof DOMElement) {
                $captionEl->textContent = $label;
            } else {
                $this->writeCaption($table, 'caption', $document, $label, prepend: true);
            }
        }
    }

    /**
     * Snapshot a live element list into a stable array before mutating the DOM.
     *
     * @return list<DOMElement>
     */
    protected function collect(DOMDocument $document, string $tag): array
    {
        $elements = [];

        foreach ($document->getElementsByTagName($tag) as $element) {
            if ($element instanceof DOMElement) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    /**
     * Remove an auto-generated label the caption may already carry (e.g. a
     * stored "Figure 1: " or "Table 2 -") so the fresh number isn't doubled.
     * Only strips when a digit follows, leaving real captions like
     * "Figure of speech" untouched.
     */
    protected function stripLabelPrefix(string $caption, string $kind): string
    {
        return trim((string) preg_replace('/^\s*'.$kind.'\s*\d+\s*[:.\-]?\s*/i', '', $caption));
    }

    protected function captionText(DOMNodeList $list): string
    {
        $node = $list->item(0);

        return $node ? trim($node->textContent) : '';
    }

    protected function writeCaption(DOMElement $parent, string $tag, DOMDocument $document, string $text, bool $prepend = false): void
    {
        $existing = $parent->getElementsByTagName($tag)->item(0);

        if ($existing instanceof DOMElement) {
            $existing->textContent = $text;

            return;
        }

        $node = $document->createElement($tag);
        $node->textContent = $text;

        if ($prepend && $parent->firstChild) {
            $parent->insertBefore($node, $parent->firstChild);
        } else {
            $parent->appendChild($node);
        }
    }

    protected function innerHtml(DOMElement $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return $html;
    }

    /**
     * Replace every `<span class="ref-cite" data-ref-id="X">` with the inline
     * citation for the current format, and track which references appear so
     * the bibliography lists only the cited ones.
     */
    protected function renderCitations(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//span[contains(concat(" ", normalize-space(@class), " "), " ref-cite ")]');

        if (! $nodes instanceof DOMNodeList) {
            return;
        }

        foreach (iterator_to_array($nodes) as $span) {
            if (! $span instanceof DOMElement) {
                continue;
            }

            $id = (int) $span->getAttribute('data-ref-id');
            $reference = $this->references->firstWhere('id', $id);

            $text = $reference instanceof Reference
                ? $this->citationFormatter->inline($reference)
                : '[?]';

            if (! $reference instanceof Reference) {
                $span->parentNode?->replaceChild($document->createTextNode($text), $span);

                continue;
            }

            $this->usedReferenceIds[(int) $reference->id] = true;

            // Wrap the citation in an anchor so the reader can jump to the
            // matching bibliography entry. The target id is set later when the
            // references list is rendered.
            $anchor = $document->createElement('a');
            $anchor->setAttribute('href', '#ref-'.$reference->id);
            $anchor->setAttribute('class', 'ref-cite-link');
            $anchor->appendChild($document->createTextNode($text));

            $span->parentNode?->replaceChild($anchor, $span);
        }
    }

    /**
     * Replace the `[data-references-list]` placeholder element with a
     * bibliography block listing only the references that were cited.
     */
    protected function renderReferencesPlaceholder(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//*[@data-references-list]');

        if (! $nodes instanceof DOMNodeList || $nodes->length === 0) {
            return;
        }

        $html = $this->buildReferencesHtml();

        foreach (iterator_to_array($nodes) as $node) {
            if (! $node instanceof DOMElement || $node->parentNode === null) {
                continue;
            }

            $wrapper = $this->parseFragment($document, $html);

            if ($wrapper === null) {
                continue;
            }

            $node->parentNode->replaceChild($wrapper, $node);
        }
    }

    /**
     * Build the HTML for the bibliography block — only references that were
     * cited in the body are included, sorted alphabetically by first author.
     */
    public function buildReferencesHtml(): string
    {
        $usedIds = array_keys($this->usedReferenceIds);
        $used = $this->references->filter(fn (Reference $r) => \in_array((int) $r->id, $usedIds, true));
        $sorted = $used->sortBy(fn (Reference $r) => $r->sortKey())->values();

        if ($sorted->isEmpty()) {
            return '<div class="report-references"><p class="report-references-empty"><em>No references cited yet.</em></p></div>';
        }

        $html = '<div class="report-references">';

        foreach ($sorted as $reference) {
            $marker = $this->citationFormatter->bibliographyMarker($reference);
            $entry = $this->citationFormatter->bibliography($reference);
            $html .= '<p class="report-reference" id="ref-'.$reference->id.'">'.$marker.$entry.'</p>';
        }

        return $html.'</div>';
    }

    /**
     * Parse an HTML snippet and import the wrapping element into $document.
     */
    protected function parseFragment(DOMDocument $document, string $html): ?DOMElement
    {
        $temp = new DOMDocument;
        libxml_use_internal_errors(true);
        $temp->loadHTML(
            '<?xml encoding="UTF-8"><div id="ref-wrap">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $wrap = $temp->getElementById('ref-wrap');

        if (! $wrap instanceof DOMElement) {
            return null;
        }

        $first = $wrap->firstChild;

        if (! $first instanceof DOMElement) {
            return null;
        }

        $imported = $document->importNode($first, true);

        return $imported instanceof DOMElement ? $imported : null;
    }
}
