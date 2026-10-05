<?php
/**
 * NavImport — one-file NAV/price upload for ALL share classes.
 *
 * Reads a .xlsx or .csv file, works out which share class every value belongs
 * to (by ISIN, or by exact share-class name), validates everything, and only
 * then writes to nav_entries inside a single transaction (all-or-nothing).
 *
 * Two layouts are accepted and auto-detected from the header row:
 *   LONG  — one row per share class per date:
 *             Date | ISIN | Share class | Currency | NAV | Benchmark
 *           (ISIN or Share class required; Currency/Benchmark optional)
 *   WIDE  — one row per date, one column per share class (history grids):
 *             Date | IE0002787442 | IE00B53RTW70 | …
 *           (column headers may be an ISIN, "Name (ISIN)" or the exact name)
 *
 * The parsing/validation half (readFile, analyze, jumpWarnings) is pure and
 * has no database dependency, so it can be unit-tested from the CLI.
 *
 * Portability notes (production runs PHP 8.1 on shared hosting):
 *   - pdo_mysql there returns every column as a string → all DB values are
 *     cast explicitly before use.
 *   - ZipArchive may be missing → .xlsx gives a clear "upload CSV" message.
 */
declare(strict_types=1);

namespace Mori;

final class NavImport
{
    public const MAX_BYTES  = 15 * 1024 * 1024;   // upload size guard
    public const MAX_ROWS   = 200000;             // ~70 years of daily data × 11 classes
    public const JUMP_WARN  = 0.15;               // flag NAV moves > 15% between consecutive points
    public const MAX_NAV    = 99999999999.9999;   // DECIMAL(15,4)

    private const NS_MAIN   = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const NS_REL    = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_PKGREL = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /** Header aliases → canonical field. Compared after normaliseHeader(). */
    private const HEADER_ALIASES = [
        'date' => [
            'date', 'nav date', 'valuation date', 'valuation day', 'price date', 'pricing date',
            'dealing date', 'trade date', 'as of', 'as of date', 'as at', 'as at date',
            'datum', 'bewertungsdatum', 'stichtag', 'nav datum',
        ],
        'isin' => ['isin', 'isin code', 'isin number', 'isin nr', 'isin no'],
        'class' => [
            'share class', 'share class name', 'class', 'class name', 'name',
            'anteilsklasse', 'anteilklasse',
        ],
        'nav' => [
            'nav', 'nav per share', 'nav share', 'nav per unit', 'net asset value',
            'net asset value per share', 'price', 'nav price', 'unit price', 'share price',
            'anteilwert', 'nettoinventarwert', 'nettoinventarwert je anteil', 'nav je anteil',
            'inventarwert', 'kurs',
        ],
        'benchmark' => ['benchmark', 'benchmark value', 'benchmark index', 'index', 'index value', 'vergleichsindex'],
        'currency'  => ['currency', 'ccy', 'currency code', 'curr', 'wahrung', 'waehrung', 'wahrungscode'],
    ];

    // ======================================================================
    // 1. READING
    // ======================================================================

    /**
     * Read an uploaded file into rows.
     * @return array{rows: list<array{n:int, cells:list<string>}>, source:string, delimiter:?string}
     */
    public static function readFile(string $path, string $originalName): array
    {
        $ext  = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $size = @filesize($path);
        if ($size === false || $size === 0) {
            throw new \RuntimeException('The file is empty.');
        }
        if ($size > self::MAX_BYTES) {
            throw new \RuntimeException('The file is too large (max ' . (self::MAX_BYTES / 1048576) . ' MB).');
        }
        if ($ext === 'xls') {
            throw new \RuntimeException('Old Excel 97–2003 (.xls) files cannot be read. In Excel use File → Save As → "Excel Workbook (.xlsx)" or "CSV UTF-8", then upload again.');
        }
        if ($ext === 'ods' || $ext === 'numbers') {
            throw new \RuntimeException('Please save the file as Excel (.xlsx) or CSV and upload again.');
        }

        $head  = (string) @file_get_contents($path, false, null, 0, 4);
        $isZip = str_starts_with($head, "PK\x03\x04");

        if ($isZip) {
            return self::readXlsx($path);
        }
        if (in_array($ext, ['xlsx', 'xlsm'], true)) {
            throw new \RuntimeException('This does not look like a valid Excel (.xlsx) file. Try saving it again, or upload a CSV instead.');
        }
        return self::readCsv($path);
    }

    /** @return array{rows: list<array{n:int, cells:list<string>}>, source:string, delimiter:?string} */
    private static function readCsv(string $path): array
    {
        $raw = (string) file_get_contents($path);

        // Encoding: UTF-16 (Excel "Unicode Text"), UTF-8 BOM, or legacy Windows-1252.
        if (str_starts_with($raw, "\xFF\xFE")) {
            $raw = (string) mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($raw, "\xFE\xFF")) {
            $raw = (string) mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
        } elseif (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = (string) mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        // Excel's optional first line "sep=;" names the delimiter explicitly.
        $delim = null;
        if (preg_match('/^sep=(.)\n/i', $raw, $m)) {
            $delim = $m[1];
            $raw   = substr($raw, strlen($m[0]));
        }
        if ($delim === null) {
            $delim = self::detectDelimiter($raw);
        }

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);

        $rows = [];
        $n = 0;
        while (($cells = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
            $n++;
            if ($cells === [null]) continue;                      // blank line
            $cells = array_map(fn($c) => self::cleanCell((string) $c), $cells);
            if (implode('', $cells) === '') continue;
            $rows[] = ['n' => $n, 'cells' => array_values($cells)];
            if (count($rows) > self::MAX_ROWS) {
                fclose($fh);
                throw new \RuntimeException('The file has more than ' . number_format(self::MAX_ROWS) . ' rows.');
            }
        }
        fclose($fh);

        return ['rows' => $rows, 'source' => 'csv', 'delimiter' => $delim];
    }

    /** Pick the delimiter from the header line (first non-empty, non-comment line). */
    private static function detectDelimiter(string $raw): string
    {
        foreach (explode("\n", $raw) as $line) {
            $t = trim($line);
            if ($t === '' || str_starts_with($t, '#')) continue;
            $unquoted = (string) preg_replace('/"[^"]*"/', '', $t);
            $best = ','; $bestCount = 0;
            foreach ([',', ';', "\t", '|'] as $cand) {
                $c = substr_count($unquoted, $cand);
                if ($c > $bestCount) { $best = $cand; $bestCount = $c; }
            }
            return $best;
        }
        return ',';
    }

    /** @return array{rows: list<array{n:int, cells:list<string>}>, source:string, delimiter:?string} */
    private static function readXlsx(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Excel files cannot be read on this server. Please save the file as CSV and upload that instead.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('The Excel file could not be opened. Try saving it again, or upload a CSV instead.');
        }

        try {
            [$sheetPath, $sharedPath] = self::xlsxLocateParts($zip);
            $shared = $sharedPath ? self::xlsxSharedStrings($zip, $sharedPath) : [];
            $sheet  = self::xlsxLoad($zip, $sheetPath);
            if (!$sheet) {
                throw new \RuntimeException('The first worksheet of the Excel file could not be read.');
            }
            $ns = self::xmlNs($sheet);

            $rows = [];
            $auto = 0;
            $sheetData = $sheet->children($ns)->sheetData;
            foreach ($sheetData->children($ns)->row as $row) {
                $attr = $row->attributes();
                $rn   = isset($attr['r']) ? (int) (string) $attr['r'] : $auto + 1;
                $auto = $rn;

                $cells = [];
                $col = 0;
                foreach ($row->children($ns)->c as $c) {
                    $ca  = $c->attributes();
                    $ref = isset($ca['r']) ? (string) $ca['r'] : '';
                    if ($ref !== '' && preg_match('/^([A-Z]+)/i', $ref, $mm)) {
                        $col = self::colIndex(strtoupper($mm[1]));
                    }
                    $type = isset($ca['t']) ? (string) $ca['t'] : '';
                    $kids = $c->children($ns);
                    switch ($type) {
                        case 's':
                            $v = $shared[(int) (string) $kids->v] ?? '';
                            break;
                        case 'inlineStr':
                            $v = self::xlsxRichText($kids->is, $ns);
                            break;
                        case 'b':
                            $v = ((string) $kids->v) === '1' ? 'TRUE' : 'FALSE';
                            break;
                        case 'e':
                            $v = '#' . ltrim((string) $kids->v, '#');   // #N/A, #VALUE! …
                            break;
                        default:                                     // n, str, d
                            $v = (string) $kids->v;
                    }
                    // A formula whose result was never saved (some generators
                    // skip it) must not be mistaken for an empty cell.
                    if ($v === '' && isset($kids->f) && trim((string) $kids->f) !== '') {
                        $v = '#FORMULA';
                    }
                    $cells[$col] = self::cleanCell($v);
                    $col++;
                }
                if (!$cells) continue;
                $max = max(array_keys($cells));
                $dense = [];
                for ($i = 0; $i <= $max; $i++) $dense[] = $cells[$i] ?? '';
                if (implode('', $dense) === '') continue;
                $rows[] = ['n' => $rn, 'cells' => $dense];
                if (count($rows) > self::MAX_ROWS) {
                    throw new \RuntimeException('The file has more than ' . number_format(self::MAX_ROWS) . ' rows.');
                }
            }
            return ['rows' => $rows, 'source' => 'xlsx', 'delimiter' => null];
        } finally {
            $zip->close();
        }
    }

    /** @return array{0:string, 1:?string} [first visible worksheet path, sharedStrings path] */
    private static function xlsxLocateParts(\ZipArchive $zip): array
    {
        $sheetPath  = 'xl/worksheets/sheet1.xml';
        $sharedPath = $zip->locateName('xl/sharedStrings.xml') !== false ? 'xl/sharedStrings.xml' : null;

        $wb   = self::xlsxLoad($zip, 'xl/workbook.xml');
        $rels = self::xlsxLoad($zip, 'xl/_rels/workbook.xml.rels');
        if (!$wb || !$rels) return [$sheetPath, $sharedPath];

        $targets = [];
        foreach ($rels->children(self::xmlNs($rels))->Relationship as $r) {
            $a = $r->attributes();
            $target = (string) ($a['Target'] ?? '');
            $target = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
            $targets[(string) ($a['Id'] ?? '')] = $target;
            if (str_ends_with((string) ($a['Type'] ?? ''), '/sharedStrings')) {
                $sharedPath = $target;
            }
        }

        $wbNs   = self::xmlNs($wb);
        $sheets = $wb->children($wbNs)->sheets;
        if ($sheets !== null) {
            foreach ($sheets->children($wbNs)->sheet as $s) {
                $state = (string) ($s->attributes()['state'] ?? '');
                if ($state === 'hidden' || $state === 'veryHidden') continue;
                $rid = '';
                foreach ([self::NS_REL, 'http://purl.oclc.org/ooxml/officeDocument/relationships'] as $relNs) {
                    $ra = $s->attributes($relNs);
                    if ($ra !== null && isset($ra['id'])) { $rid = (string) $ra['id']; break; }
                }
                if ($rid !== '' && isset($targets[$rid])) {
                    $sheetPath = $targets[$rid];
                }
                break;   // first visible sheet only
            }
        }
        return [$sheetPath, $sharedPath];
    }

    /** @return list<string> */
    private static function xlsxSharedStrings(\ZipArchive $zip, string $path): array
    {
        $xml = self::xlsxLoad($zip, $path);
        if (!$xml) return [];
        $ns  = self::xmlNs($xml);
        $out = [];
        foreach ($xml->children($ns)->si as $si) {
            $out[] = self::xlsxRichText($si, $ns);
        }
        return $out;
    }

    /** Text of an <si>/<is> node: either a single <t> or rich-text runs <r><t>. */
    private static function xlsxRichText(?\SimpleXMLElement $node, string $ns): string
    {
        if ($node === null) return '';
        $k = $node->children($ns);
        if (isset($k->t)) return (string) $k->t;
        $s = '';
        foreach ($k->r as $run) {
            $s .= (string) $run->children($ns)->t;
        }
        return $s;
    }

    private static function xlsxLoad(\ZipArchive $zip, string $name): ?\SimpleXMLElement
    {
        $stat = $zip->statName($name);
        if ($stat === false) return null;
        if ((int) $stat['size'] > 80 * 1024 * 1024) {
            throw new \RuntimeException('The Excel file is too large to process.');
        }
        $data = $zip->getFromName($name);
        if ($data === false || $data === '') return null;
        $prev = libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($data, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $xml === false ? null : $xml;
    }

    /** Namespace URI of the root element (default or prefixed, transitional or strict). */
    private static function xmlNs(\SimpleXMLElement $xml): string
    {
        $ns = $xml->getNamespaces(false);
        return $ns ? (string) reset($ns) : '';
    }

    private static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }

    private static function cleanCell(string $v): string
    {
        // NBSP / narrow NBSP / zero-width chars → normal, then trim
        $v = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xE2\x80\x8B", "\xEF\xBB\xBF"], [' ', ' ', '', ''], $v);
        return trim($v, " \t\n\r\0\x0B");
    }

    // ======================================================================
    // 2. VALUE PARSING
    // ======================================================================

    /**
     * Parse a price. Accepts 142.86 · 142,86 · 1,234.56 · 1.234,56 · 1 234,56 ·
     * 1'234.56 · "EUR 142.86" · 1.4286E2.
     * @return float|null|false  null = empty cell, false = not a number
     */
    public static function parseNumber(string $s): float|null|false
    {
        $s = self::cleanCell($s);
        if ($s === '' || $s === '-' || $s === '—') return null;

        // strip currency codes / symbols around the number
        $s = (string) preg_replace('/^(EUR|USD|GBP|CHF|€|\$|£)\s*/iu', '', $s);
        $s = (string) preg_replace('/\s*(EUR|USD|GBP|CHF|€|\$|£)$/iu', '', $s);
        $s = str_replace([' ', "'", '’'], '', $s);

        if (preg_match('/^[+-]?\d+(\.\d+)?(e[+-]?\d+)?$/i', $s)) {
            return (float) $s;                                  // 142.86 · 1.4286E2
        }
        // Thousands separators must form proper 3-digit groups, otherwise the
        // value is rejected (so "12..3" or "1,23,4" never become a price).
        if (preg_match('/^([+-]?\d{1,3}(?:\.\d{3})+),(\d+)$/', $s, $m)) {      // 1.234,56
            return (float) (str_replace('.', '', $m[1]) . '.' . $m[2]);
        }
        if (preg_match('/^([+-]?\d{1,3}(?:,\d{3})+)\.(\d+)$/', $s, $m)) {      // 1,234.56
            return (float) (str_replace(',', '', $m[1]) . '.' . $m[2]);
        }
        if (preg_match('/^[+-]?\d+,\d+$/', $s)) {                               // 142,86
            return (float) str_replace(',', '.', $s);
        }
        if (preg_match('/^[+-]?\d{1,3}(?:,\d{3}){2,}$/', $s)) {                 // 1,234,567
            return (float) str_replace(',', '', $s);
        }
        if (preg_match('/^[+-]?\d{1,3}(?:\.\d{3}){2,}$/', $s)) {                // 1.234.567
            return (float) str_replace('.', '', $s);
        }
        return false;
    }

    /**
     * Parse a date to Y-m-d.
     * @param string $slashOrder 'dmy' (UK/EU, default) or 'mdy' (US) for a/b/yyyy dates
     * @return string|null|false null = empty, false = not a valid date
     */
    public static function parseDate(string $s, string $slashOrder = 'dmy'): string|null|false
    {
        $s = self::cleanCell($s);
        if ($s === '') return null;

        // Excel serial date (e.g. 46298 or 46298.5). Range ≈ 1954 … 2119.
        if (preg_match('/^\d{5}(\.\d+)?$/', $s)) {
            $serial = (int) floor((float) $s);
            if ($serial >= 20000 && $serial <= 80000) {
                return (new \DateTimeImmutable('1899-12-30'))->modify('+' . $serial . ' days')->format('Y-m-d');
            }
            return false;
        }
        // 20261003
        if (preg_match('/^(19|20)(\d{2})(\d{2})(\d{2})$/', $s, $m)) {
            return self::ymd((int) ($m[1] . $m[2]), (int) $m[3], (int) $m[4]);
        }
        // 2026-10-03 / 2026/10/03 / 2026.10.03 (+ optional time part)
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})(?:[ T].*)?$/', $s, $m)) {
            return self::ymd((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        // a/b/yyyy, a.b.yyyy, a-b-yyyy (+ optional time part)
        if (preg_match('/^(\d{1,2})([\/.\-])(\d{1,2})\2(\d{2}|\d{4})(?:[ T].*)?$/', $s, $m)) {
            $a = (int) $m[1]; $b = (int) $m[3]; $y = (int) $m[4];
            if ($y < 100) $y += ($y >= 70 ? 1900 : 2000);
            if ($m[2] === '/' && $slashOrder === 'mdy') {
                return self::ymd($y, $a, $b);
            }
            return self::ymd($y, $b, $a);                     // day first
        }
        // Textual months: "3 Oct 2026", "03-Oct-26", "October 3, 2026", German "3. Okt. 2026".
        // Strict explicit formats only — never strtotime(), which would turn
        // words like "now" or "next monday" into a date.
        if (preg_match('/\d/', $s) && preg_match('/[a-zäöü]/iu', $s)) {
            $t = mb_strtolower($s);
            $de = [
                'januar' => 'january', 'februar' => 'february', 'märz' => 'march', 'maerz' => 'march',
                'mai' => 'may', 'juni' => 'june', 'juli' => 'july', 'oktober' => 'october',
                'dezember' => 'december', 'mrz' => 'mar', 'mär' => 'mar', 'okt' => 'oct', 'dez' => 'dec',
            ];
            $t = (string) preg_replace_callback('/\p{L}+/u', fn($w) => $de[$w[0]] ?? $w[0], $t);
            $t = (string) preg_replace('/[\s.,\-\/]+/', ' ', $t);           // "3. oct. 2026" → "3 oct 2026"
            $t = trim((string) preg_replace('/(\d)(st|nd|rd|th)\b/', '$1', $t));
            $t = ucwords($t);
            foreach (['j M Y', 'j M y', 'j F Y', 'j F y', 'M j Y', 'M j y', 'F j Y', 'F j y'] as $fmt) {
                $dt = \DateTimeImmutable::createFromFormat('!' . $fmt, $t);
                $err = \DateTimeImmutable::getLastErrors();
                if ($dt !== false && (!$err || ($err['warning_count'] === 0 && $err['error_count'] === 0))) {
                    $ymd = self::ymd((int) $dt->format('Y'), (int) $dt->format('n'), (int) $dt->format('j'));
                    if ($ymd !== false) return $ymd;            // 'Y' reads "26" as year 26 → try 'y' next
                }
            }
        }
        return false;
    }

    private static function ymd(int $y, int $m, int $d): string|false
    {
        if ($y < 1900 || $y > 2200 || !checkdate($m, $d, $y)) return false;
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    /** Lowercase, strip accents-ish noise, drop "(…)" hints and punctuation. */
    private static function normaliseHeader(string $h): string
    {
        $h = mb_strtolower(self::cleanCell($h));
        $h = strtr($h, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss']);
        $h = (string) preg_replace('/\([^)]*\)/', ' ', $h);      // "Benchmark (optional)" → "benchmark"
        $h = (string) preg_replace('/[^a-z0-9]+/', ' ', $h);
        return trim((string) preg_replace('/\s+/', ' ', $h));
    }

    private static function normaliseName(string $n): string
    {
        $n = mb_strtolower(self::cleanCell($n));
        $n = (string) preg_replace('/[^a-z0-9]+/', ' ', $n);
        return trim($n);
    }

    private static function normaliseIsin(string $s): string
    {
        return strtoupper((string) preg_replace('/[\s\-]+/', '', $s));
    }

    private static function looksLikeIsin(string $s): bool
    {
        return (bool) preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', $s);
    }

    // ======================================================================
    // 3. ANALYSIS (pure — no database)
    // ======================================================================

    /**
     * Validate rows against the known share classes.
     *
     * @param list<array{n:int, cells:list<string>}> $rows
     * @param list<array<string,mixed>> $classes  share_classes rows (id, name, isin, currency)
     * @param string $today  Y-m-d (server date) — later dates are rejected
     * @return array{
     *   format: string, entries: list<array{sc:int, date:string, nav:float, bench:?float, row:int}>,
     *   errors: list<string>, warnings: list<string>, notes: list<string>,
     *   skipped_blank: int, has_benchmark: bool
     * }
     */
    public static function analyze(array $rows, array $classes, string $today): array
    {
        $res = [
            'format' => '', 'entries' => [], 'errors' => [], 'warnings' => [], 'notes' => [],
            'skipped_blank' => 0, 'has_benchmark' => false,
        ];

        // Index share classes
        $byId = $byIsin = $byName = [];
        foreach ($classes as $c) {
            $id = (int) $c['id'];
            $byId[$id] = $c;
            $isin = self::normaliseIsin((string) ($c['isin'] ?? ''));
            if ($isin !== '') $byIsin[$isin] = $id;
            $byName[self::normaliseName((string) $c['name'])] = $id;
        }
        $label = fn(int $id): string => (string) $byId[$id]['name'] . (!empty($byId[$id]['isin']) ? ' (' . $byId[$id]['isin'] . ')' : '');

        // Drop comment rows ("# …")
        $rows = array_values(array_filter($rows, fn($r) => !str_starts_with((string) ($r['cells'][0] ?? ''), '#')));
        if (!$rows) {
            $res['errors'][] = 'The file contains no data.';
            return $res;
        }

        // ---- Header -------------------------------------------------------
        $header = $rows[0];
        $dataRows = array_slice($rows, 1);
        $field = [];               // canonical field => column index
        $unknownCols = [];         // index => original header text
        foreach ($header['cells'] as $i => $h) {
            $norm = self::normaliseHeader($h);
            if ($norm === '') continue;
            $matched = false;
            foreach (self::HEADER_ALIASES as $canon => $aliases) {
                if (in_array($norm, $aliases, true)) {
                    if (!isset($field[$canon])) $field[$canon] = $i;
                    $matched = true;
                    break;
                }
            }
            if (!$matched) $unknownCols[$i] = $h;
        }

        if (!isset($field['date'])) {
            $res['errors'][] = 'No "Date" column found in the first row. The first row must contain the column titles (e.g. Date, ISIN, NAV) — please use the template.';
            return $res;
        }

        // ---- Layout ---------------------------------------------------------
        $wideCols = [];            // column index => share class id
        if (isset($field['nav'])) {
            if (!isset($field['isin']) && !isset($field['class'])) {
                $res['errors'][] = 'The file has a NAV column but no "ISIN" or "Share class" column, so the prices cannot be matched to share classes.';
                return $res;
            }
            $res['format'] = 'long';
            $res['has_benchmark'] = isset($field['benchmark']);
            foreach ($unknownCols as $h) {
                $res['notes'][] = 'Column "' . $h . '" is not used and was ignored.';
            }
        } else {
            $res['format'] = 'wide';
            $seen = [];
            foreach ($unknownCols as $i => $h) {
                $up = self::normaliseIsin($h);
                $id = null;
                if (preg_match('/\b([A-Z]{2}[A-Z0-9]{9}[0-9])\b/', strtoupper($h), $mm)) {
                    $id = $byIsin[$mm[1]] ?? null;
                    if ($id === null) {
                        $res['errors'][] = 'Column "' . $h . '": ISIN ' . $mm[1] . ' does not belong to any share class.';
                        continue;
                    }
                } elseif (isset($byName[self::normaliseName($h)])) {
                    $id = $byName[self::normaliseName($h)];
                } elseif (self::looksLikeIsin($up)) {
                    $res['errors'][] = 'Column "' . $h . '" looks like an ISIN but does not belong to any share class.';
                    continue;
                }
                if ($id === null) {
                    $res['notes'][] = 'Column "' . $h . '" does not match any share class and was ignored.';
                    continue;
                }
                if (isset($seen[$id])) {
                    $res['errors'][] = 'Share class ' . $label($id) . ' appears in two columns ("' . $seen[$id] . '" and "' . $h . '").';
                    continue;
                }
                $seen[$id] = $h;
                $wideCols[$i] = $id;
            }
            foreach (['isin', 'class', 'currency', 'benchmark'] as $f) {
                if (isset($field[$f])) {
                    $res['notes'][] = 'Column "' . $header['cells'][$field[$f]] . '" was ignored (no NAV column found, so the file was read as one column per share class).';
                }
            }
            if (!$wideCols && !$res['errors']) {
                $res['errors'][] = 'No NAV column found, and none of the column titles match a share class. Use a "NAV" column with an "ISIN" column, or one column per share class titled with its ISIN — see the templates.';
            }
            if ($res['errors']) return $res;
        }

        // ---- Slash-date order (UK 03/10/2026 vs US 10/03/2026) --------------
        $dateCol = $field['date'];
        $dmyEvidence = $mdyEvidence = null;
        $ambiguous = false;
        foreach ($dataRows as $r) {
            $v = (string) ($r['cells'][$dateCol] ?? '');
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2}|\d{4})\b/', $v, $m)) {
                $a = (int) $m[1]; $b = (int) $m[2];
                if ($a > 12 && $b <= 12) { $dmyEvidence ??= $v; }
                elseif ($b > 12 && $a <= 12) { $mdyEvidence ??= $v; }
                elseif ($a <= 12 && $b <= 12 && $a !== $b) { $ambiguous = true; }
            }
        }
        $slashOrder = 'dmy';
        if ($dmyEvidence !== null && $mdyEvidence !== null) {
            $res['errors'][] = 'The Date column mixes day/month/year ("' . $dmyEvidence . '") and month/day/year ("' . $mdyEvidence . '") formats. Please use one format — ideally YYYY-MM-DD.';
            return $res;
        }
        if ($mdyEvidence !== null) {
            $slashOrder = 'mdy';
            $res['warnings'][] = 'Dates were read as MONTH/DAY/YEAR (US format) because the file contains "' . $mdyEvidence . '". Please check the dates in the summary below.';
        } elseif ($ambiguous && $dmyEvidence === null) {
            $res['notes'][] = 'Dates such as 03/10/2026 were read as DAY/MONTH/YEAR (3 October 2026). Check the dates in the summary below.';
        }

        // ---- Rows -----------------------------------------------------------
        $entries = [];             // "sc|date" => entry
        $errors  = [];
        $decimalsNote = false;
        $addError = function (string $msg) use (&$errors) { $errors[] = $msg; };

        $parseNav = function (string $raw, int $rowN, string $who) use ($addError, &$decimalsNote): float|null|false {
            if ($raw === '#FORMULA') {
                $addError("Row {$rowN}: the NAV for {$who} is a formula without a saved result — open the file in Excel, save it and upload it again (or paste the values as numbers).");
                return false;
            }
            $nav = self::parseNumber($raw);
            if ($nav === null) return null;
            if ($nav === false) { $addError("Row {$rowN}: NAV \"{$raw}\" for {$who} is not a number."); return false; }
            if ($nav <= 0)      { $addError("Row {$rowN}: NAV {$raw} for {$who} must be greater than zero."); return false; }
            if ($nav > self::MAX_NAV) { $addError("Row {$rowN}: NAV {$raw} for {$who} is too large."); return false; }
            if (preg_match('/[.,](\d{5,})$/', str_replace(' ', '', $raw))) $decimalsNote = true;
            return $nav;
        };
        $checkDate = function (string $raw, int $rowN) use ($slashOrder, $today, $addError): string|null|false {
            $d = self::parseDate($raw, $slashOrder);
            if ($d === null) return null;
            if ($d === false) { $addError("Row {$rowN}: \"{$raw}\" is not a valid date (use YYYY-MM-DD, e.g. 2026-10-03)."); return false; }
            if ($d > $today)  { $addError("Row {$rowN}: date {$d} is in the future."); return false; }
            if ($d < '1990-01-01') { $addError("Row {$rowN}: date {$d} is before 1990 — please check it."); return false; }
            return $d;
        };
        $put = function (int $sc, string $date, float $nav, ?float $bench, int $rowN) use (&$entries, $addError, $label, &$res) {
            $k = $sc . '|' . $date;
            if (isset($entries[$k])) {
                $prev = $entries[$k];
                if (abs($prev['nav'] - $nav) < 0.00005 && $prev['bench'] === $bench) {
                    $res['warnings'][] = "Row {$rowN}: duplicate of row {$prev['row']} ({$label($sc)}, {$date}) — counted once.";
                    return;
                }
                $addError("Rows {$prev['row']} and {$rowN}: two different NAVs for {$label($sc)} on {$date}.");
                return;
            }
            $entries[$k] = ['sc' => $sc, 'date' => $date, 'nav' => $nav, 'bench' => $bench, 'row' => $rowN];
        };

        foreach ($dataRows as $r) {
            $n = (int) $r['n'];
            $cells = $r['cells'];
            $get = fn(?int $i): string => $i === null ? '' : (string) ($cells[$i] ?? '');

            if ($res['format'] === 'long') {
                $rawNav  = $get($field['nav']);
                $rawIsin = $get($field['isin'] ?? null);
                $rawName = $get($field['class'] ?? null);
                $rawDate = $get($dateCol);

                // Identify share class
                $sc = null;
                if ($rawIsin !== '') {
                    $isin = self::normaliseIsin($rawIsin);
                    $sc = $byIsin[$isin] ?? null;
                    if ($sc === null) {
                        if (self::parseNumber($rawNav) === null) { $res['skipped_blank']++; continue; }
                        $addError("Row {$n}: ISIN \"{$rawIsin}\" does not belong to any share class.");
                        continue;
                    }
                    if ($rawName !== '' && isset($byName[self::normaliseName($rawName)]) && $byName[self::normaliseName($rawName)] !== $sc) {
                        $addError("Row {$n}: ISIN {$isin} and share class \"{$rawName}\" refer to different share classes.");
                        continue;
                    }
                } elseif ($rawName !== '') {
                    $sc = $byName[self::normaliseName($rawName)] ?? null;
                    if ($sc === null) {
                        if (self::parseNumber($rawNav) === null) { $res['skipped_blank']++; continue; }
                        $addError("Row {$n}: share class \"{$rawName}\" not recognised — add the ISIN column to be safe.");
                        continue;
                    }
                } else {
                    if (self::parseNumber($rawNav) === null) { $res['skipped_blank']++; continue; }
                    $addError("Row {$n}: no ISIN / share class given.");
                    continue;
                }

                $nav = $parseNav($rawNav, $n, $label($sc));
                if ($nav === null) { $res['skipped_blank']++; continue; }
                if ($nav === false) continue;

                $date = $checkDate($rawDate, $n);
                if ($date === null) { $addError("Row {$n}: date is missing for {$label($sc)}."); continue; }
                if ($date === false) continue;

                if (isset($field['currency'])) {
                    $ccy = strtoupper($get($field['currency']));
                    if ($ccy !== '' && $ccy !== strtoupper((string) $byId[$sc]['currency'])) {
                        $addError("Row {$n}: currency {$ccy} does not match {$label($sc)}, which is priced in {$byId[$sc]['currency']}.");
                        continue;
                    }
                }

                $bench = null;
                if (isset($field['benchmark'])) {
                    $rawB = $get($field['benchmark']);
                    $b = self::parseNumber($rawB);
                    if ($b === false) { $addError("Row {$n}: benchmark \"{$rawB}\" is not a number."); continue; }
                    $bench = $b;
                }
                $put($sc, $date, $nav, $bench, $n);
            } else {
                $rawDate = $get($dateCol);
                $anyValue = false;
                foreach ($wideCols as $ci => $_) {
                    if ($get($ci) !== '') { $anyValue = true; break; }
                }
                if (!$anyValue) { if ($rawDate !== '') $res['skipped_blank']++; continue; }

                $date = $checkDate($rawDate, $n);
                if ($date === null) { $addError("Row {$n}: date is missing."); continue; }
                if ($date === false) continue;

                foreach ($wideCols as $ci => $sc) {
                    $nav = $parseNav($get($ci), $n, $label($sc));
                    if ($nav === null || $nav === false) continue;
                    $put($sc, $date, $nav, null, $n);
                }
            }
        }

        if ($decimalsNote) {
            $res['notes'][] = 'Some NAVs have more than 4 decimal places — they are stored rounded to 4 decimals.';
        }
        $res['errors'] = $errors;

        $list = array_values($entries);
        usort($list, fn($a, $b) => [$a['sc'], $a['date']] <=> [$b['sc'], $b['date']]);
        $res['entries'] = $list;

        if (!$errors && !$list) {
            $res['errors'][] = 'No prices found in the file — every NAV cell is empty.';
        }
        return $res;
    }

    /**
     * Flag suspicious NAV moves (typos, wrong decimal separator, wrong class).
     * @param callable(int $scId, string $date): ?float $prevNav  stored NAV strictly before $date
     * @param array<int,string> $names  share class id => display label
     * @return list<string>
     */
    public static function jumpWarnings(array $entries, callable $prevNav, array $names): array
    {
        $out = [];
        $bySc = [];
        foreach ($entries as $e) $bySc[$e['sc']][] = $e;
        foreach ($bySc as $sc => $list) {
            $prev = $prevNav((int) $sc, $list[0]['date']);
            $prevDate = null;
            $count = 0;
            foreach ($list as $e) {
                if ($prev !== null && $prev > 0) {
                    $chg = $e['nav'] / $prev - 1;
                    if (abs($chg) > self::JUMP_WARN) {
                        if ($count < 3) {
                            $out[] = sprintf(
                                '%s: NAV moves from %s to %s (%+.1f%%) on %s%s — please double-check (decimal separator, share class, currency).',
                                $names[$sc] ?? ('#' . $sc),
                                self::fmt($prev), self::fmt($e['nav']), $chg * 100, $e['date'],
                                $prevDate ? '' : ' versus the last published price'
                            );
                        }
                        $count++;
                    }
                }
                $prev = $e['nav'];
                $prevDate = $e['date'];
            }
            if ($count > 3) {
                $out[] = ($names[$sc] ?? ('#' . $sc)) . ': ' . ($count - 3) . ' more large moves not listed.';
            }
        }
        return $out;
    }

    private static function fmt(float $v): string
    {
        $s = number_format($v, 4, '.', ',');
        return rtrim(rtrim($s, '0'), '.') ?: '0';
    }

    // ======================================================================
    // 4. DATABASE: preview diff + apply
    // ======================================================================

    /** All share classes with their fund name, in display order. */
    public static function classes(Database $db): array
    {
        return $db->fetchAll(
            'SELECT sc.id, sc.name, sc.isin, sc.currency, sc.status, sc.display_order, f.name_en AS fund_name
               FROM share_classes sc JOIN funds f ON f.id = sc.fund_id
              ORDER BY f.display_order, sc.display_order, sc.id'
        );
    }

    /**
     * Compare file entries with what is stored.
     * @return array<int, array{rows:int, first:string, last:string, last_nav:float, new:int, changed:int, unchanged:int, existing_total:int, prev_nav:?float, prev_date:?string}>
     */
    public static function diff(Database $db, array $entries): array
    {
        $bySc = [];
        foreach ($entries as $e) $bySc[$e['sc']][] = $e;
        $out = [];
        foreach ($bySc as $sc => $list) {
            $first = $list[0]['date'];
            $last  = $list[count($list) - 1]['date'];
            $stored = [];
            foreach ($db->fetchAll(
                'SELECT entry_date, nav FROM nav_entries WHERE share_class_id = :s AND entry_date BETWEEN :a AND :b',
                ['s' => $sc, 'a' => $first, 'b' => $last]
            ) as $row) {
                $stored[(string) $row['entry_date']] = (float) $row['nav'];
            }
            $new = $changed = $unchanged = 0;
            foreach ($list as $e) {
                if (!array_key_exists($e['date'], $stored)) $new++;
                elseif (abs($stored[$e['date']] - round($e['nav'], 4)) >= 0.00005) $changed++;
                else $unchanged++;
            }
            $prev = $db->fetchOne(
                'SELECT entry_date, nav FROM nav_entries WHERE share_class_id = :s AND entry_date < :d ORDER BY entry_date DESC LIMIT 1',
                ['s' => $sc, 'd' => $first]
            );
            $out[(int) $sc] = [
                'rows' => count($list), 'first' => $first, 'last' => $last,
                'last_nav' => (float) $list[count($list) - 1]['nav'],
                'new' => $new, 'changed' => $changed, 'unchanged' => $unchanged,
                'existing_total' => (int) $db->fetchColumn('SELECT COUNT(*) FROM nav_entries WHERE share_class_id = :s', ['s' => $sc]),
                'prev_nav'  => $prev ? (float) $prev['nav'] : null,
                'prev_date' => $prev ? (string) $prev['entry_date'] : null,
            ];
        }
        return $out;
    }

    /** Stored NAV strictly before a date (for jumpWarnings). */
    public static function prevNavLookup(Database $db): callable
    {
        return function (int $sc, string $date) use ($db): ?float {
            $v = $db->fetchColumn(
                'SELECT nav FROM nav_entries WHERE share_class_id = :s AND entry_date < :d ORDER BY entry_date DESC LIMIT 1',
                ['s' => $sc, 'd' => $date]
            );
            return ($v === false || $v === null) ? null : (float) $v;
        };
    }

    /**
     * Write entries in one transaction.
     *   upsert  — insert new dates, overwrite changed NAVs (default)
     *   add     — insert new dates only, keep existing values
     *   replace — delete ALL stored NAVs of the share classes in the file first
     * A blank/absent benchmark never wipes a stored benchmark value.
     * @return array{inserted:int, updated:int, unchanged:int, deleted:int}
     */
    public static function apply(Database $db, array $entries, string $mode): array
    {
        if (!in_array($mode, ['upsert', 'add', 'replace'], true)) {
            throw new \InvalidArgumentException('Unknown import mode.');
        }
        return $db->transaction(function (Database $db) use ($entries, $mode): array {
            $pdo = $db->pdo();
            $deleted = 0;
            if ($mode === 'replace') {
                $ids = array_values(array_unique(array_map(fn($e) => (int) $e['sc'], $entries)));
                if ($ids) {
                    $deleted = (int) $pdo->exec('DELETE FROM nav_entries WHERE share_class_id IN (' . implode(',', $ids) . ')');
                }
            }
            $sql = 'INSERT INTO nav_entries (share_class_id, entry_date, nav, benchmark_value) VALUES (:s, :d, :n, :b) ';
            $sql .= $mode === 'add'
                ? 'ON DUPLICATE KEY UPDATE id = id'
                : 'ON DUPLICATE KEY UPDATE nav = VALUES(nav), benchmark_value = COALESCE(VALUES(benchmark_value), benchmark_value)';
            $st = $pdo->prepare($sql);

            $ins = $upd = $same = 0;
            foreach ($entries as $e) {
                $st->execute([
                    's' => (int) $e['sc'],
                    'd' => (string) $e['date'],
                    'n' => sprintf('%.4F', round((float) $e['nav'], 4)),
                    'b' => $e['bench'] === null ? null : sprintf('%.4F', round((float) $e['bench'], 4)),
                ]);
                $rc = $st->rowCount();
                if ($rc === 1) $ins++;
                elseif ($rc === 2) $upd++;
                else $same++;
            }
            return ['inserted' => $ins, 'updated' => $upd, 'unchanged' => $same, 'deleted' => $deleted];
        });
    }

    // ======================================================================
    // 5. TEMPORARY STORAGE between "check file" and "publish"
    // ======================================================================

    private static function stashDir(): string
    {
        $candidates = [rtrim(sys_get_temp_dir(), '/\\') . '/mori-nav-import', dirname(__DIR__) . '/uploads/.nav-import'];
        foreach ($candidates as $dir) {
            if (!is_dir($dir)) @mkdir($dir, 0700, true);
            if (is_dir($dir) && is_writable($dir)) {
                if (str_contains($dir, '/uploads/') && !is_file($dir . '/.htaccess')) {
                    @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
                }
                return $dir;
            }
        }
        throw new \RuntimeException('No writable temporary folder is available on the server.');
    }

    /** Keep an uploaded file for the confirm step. Returns an opaque token. */
    public static function stash(string $uploadedTmp, string $originalName): string
    {
        $dir = self::stashDir();
        // housekeeping: drop stale files (> 6h)
        foreach (glob($dir . '/nav-*') ?: [] as $old) {
            if (@filemtime($old) < time() - 21600) @unlink($old);
        }
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $ext = in_array($ext, ['csv', 'txt', 'tsv', 'xlsx', 'xlsm'], true) ? $ext : 'dat';
        $token = bin2hex(random_bytes(16));
        $dest = $dir . '/nav-' . $token . '.' . $ext;
        $ok = is_uploaded_file($uploadedTmp) ? move_uploaded_file($uploadedTmp, $dest) : copy($uploadedTmp, $dest);
        if (!$ok) throw new \RuntimeException('The uploaded file could not be stored.');
        @chmod($dest, 0600);
        return $token;
    }

    public static function stashPath(string $token): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
        try { $dir = self::stashDir(); } catch (\Throwable) { return null; }
        $hits = glob($dir . '/nav-' . $token . '.*') ?: [];
        return $hits[0] ?? null;
    }

    public static function unstash(string $token): void
    {
        $p = self::stashPath($token);
        if ($p) @unlink($p);
    }

    // ======================================================================
    // 6. TEMPLATES
    // ======================================================================

    /** Most recent weekday on or before today. */
    public static function lastBusinessDay(?string $today = null): string
    {
        $d = new \DateTimeImmutable($today ?? 'today');
        while ((int) $d->format('N') >= 6) $d = $d->modify('-1 day');
        return $d->format('Y-m-d');
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} [header, rows] */
    public static function templateData(array $classes, string $layout, string $date): array
    {
        if ($layout === 'wide') {
            $header = ['Date'];
            $row = [['date' => $date]];
            foreach ($classes as $c) {
                $header[] = $c['name'] . ' (' . $c['isin'] . ')';
                $row[] = ['nav' => null];
            }
            return [$header, [$row]];
        }
        $header = ['Date', 'ISIN', 'Share class', 'Currency', 'NAV', 'Benchmark (optional)'];
        $rows = [];
        foreach ($classes as $c) {
            $rows[] = [['date' => $date], (string) $c['isin'], (string) $c['name'], (string) $c['currency'], ['nav' => null], ''];
        }
        return [$header, $rows];
    }

    /** Stream a ready-to-fill template (xlsx, falls back to csv) and exit. */
    public static function sendTemplate(array $classes, string $layout, string $format): never
    {
        $layout = $layout === 'wide' ? 'wide' : 'long';
        $date   = self::lastBusinessDay();
        [$header, $rows] = self::templateData($classes, $layout, $date);
        $base = $layout === 'wide' ? 'mori-nav-history-template' : 'mori-nav-prices-template';

        if ($format === 'xlsx' && class_exists(\ZipArchive::class)) {
            $tmp = tempnam(sys_get_temp_dir(), 'moriTpl');
            self::writeXlsx($tmp, $header, $rows, self::instructions($layout));
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $base . '.xlsx"');
            header('Content-Length: ' . filesize($tmp));
            header('Cache-Control: no-store');
            readfile($tmp);
            @unlink($tmp);
            exit;
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $base . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");                       // BOM → Excel opens UTF-8 correctly
        fputcsv($out, $header, ',', '"', '');
        foreach ($rows as $r) {
            fputcsv($out, array_map(fn($c) => is_array($c) ? ($c['date'] ?? '') : (string) $c, $r), ',', '"', '');
        }
        fclose($out);
        exit;
    }

    /** @return list<string> */
    private static function instructions(string $layout): array
    {
        $common = [
            'Mori Capital — NAV price upload',
            '',
            'Upload this file in the admin panel: Funds → Performance (NAV) → "Upload prices — all share classes".',
            'Only the FIRST sheet ("Prices") is read. This "How to use" sheet is ignored.',
            '',
        ];
        $specific = $layout === 'wide' ? [
            'One row per date, one column per share class (ideal for loading a price history).',
            'Column titles must contain the ISIN — do not rename them.',
            'Leave a cell empty if there is no price for that share class on that date.',
            'Date: a real Excel date or YYYY-MM-DD (e.g. 2026-10-03). Add as many rows as you like.',
        ] : [
            'One row per share class per date. The 11 share classes are pre-filled.',
            '1. Check the Date (a real Excel date or YYYY-MM-DD, e.g. 2026-10-03).',
            '2. Type each NAV per share in the NAV column (e.g. 142.8634).',
            '3. Rows with an empty NAV are skipped — so you can publish only some share classes.',
            'To add several dates in one go, copy the 11 rows below and change the date.',
            'Do not change the ISIN column — it is how prices are matched to share classes.',
            'Benchmark is optional; leaving it empty never deletes an existing benchmark value.',
        ];
        return array_merge($common, $specific, [
            '',
            'Before anything is published you will see a summary to review (new / changed prices,',
            'unusual moves). Nothing is saved until you click "Publish prices".',
        ]);
    }

    /**
     * Minimal, dependency-free XLSX writer (2 sheets: data + instructions).
     * Cells: string → inline string; ['date'=>Y-m-d] → date serial formatted yyyy-mm-dd;
     *        ['nav'=>null|float] → number cell formatted 0.0000 (empty if null).
     * @param list<string> $header
     * @param list<list<mixed>> $rows
     * @param list<string> $notes
     */
    public static function writeXlsx(string $path, array $header, array $rows, array $notes): void
    {
        $x = fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $col = function (int $i): string {
            $s = '';
            for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
            return $s;
        };
        $epoch = new \DateTimeImmutable('1899-12-30');

        // --- data sheet ---
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="' . self::NS_MAIN . '">';
        $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15"/><cols>';
        foreach ($header as $i => $h) {
            $w = max(12, min(48, mb_strlen($h) + 4));
            $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';
        $xml .= '<row r="1">';
        foreach ($header as $i => $h) {
            $xml .= '<c r="' . $col($i) . '1" t="inlineStr" s="1"><is><t>' . $x($h) . '</t></is></c>';
        }
        $xml .= '</row>';
        foreach ($rows as $ri => $r) {
            $rn = $ri + 2;
            $xml .= '<row r="' . $rn . '">';
            foreach ($r as $ci => $cell) {
                $ref = $col($ci) . $rn;
                if (is_array($cell) && isset($cell['date'])) {
                    $serial = (int) $epoch->diff(new \DateTimeImmutable($cell['date']))->days;
                    $xml .= '<c r="' . $ref . '" s="2"><v>' . $serial . '</v></c>';
                } elseif (is_array($cell) && array_key_exists('nav', $cell)) {
                    $xml .= $cell['nav'] === null
                        ? '<c r="' . $ref . '" s="3"/>'
                        : '<c r="' . $ref . '" s="3"><v>' . sprintf('%.4F', (float) $cell['nav']) . '</v></c>';
                } elseif ((string) $cell !== '') {
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . $x((string) $cell) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';

        // --- instructions sheet ---
        $help  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $help .= '<worksheet xmlns="' . self::NS_MAIN . '"><sheetFormatPr defaultRowHeight="15"/>';
        $help .= '<cols><col min="1" max="1" width="110" customWidth="1"/></cols><sheetData>';
        foreach ($notes as $i => $line) {
            $help .= '<row r="' . ($i + 1) . '">';
            if ($line !== '') {
                $help .= '<c r="A' . ($i + 1) . '" t="inlineStr"' . ($i === 0 ? ' s="1"' : '') . '><is><t xml:space="preserve">' . $x($line) . '</t></is></c>';
            }
            $help .= '</row>';
        }
        $help .= '</sheetData></worksheet>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<styleSheet xmlns="' . self::NS_MAIN . '">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="yyyy\-mm\-dd"/><numFmt numFmtId="165" formatCode="0.0000"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE8F6F3"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create the template file.');
        }
        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="' . self::NS_PKGREL . '">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<workbook xmlns="' . self::NS_MAIN . '" xmlns:r="' . self::NS_REL . '">'
            . '<bookViews><workbookView activeTab="0"/></bookViews>'
            . '<sheets><sheet name="Prices" sheetId="1" r:id="rId1"/><sheet name="How to use" sheetId="2" r:id="rId2"/></sheets>'
            . '</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="' . self::NS_PKGREL . '">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->addFromString('xl/worksheets/sheet2.xml', $help);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->close();
    }
}
