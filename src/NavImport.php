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
 * Design rule: when a value could be read two ways, the file is REJECTED with
 * an explanation rather than guessed — a wrong price must never be published
 * silently. The parsing/validation half (readFile, analyze, seriesWarnings) is
 * pure and has no database dependency, so it can be unit-tested from the CLI.
 *
 * Portability notes (production runs PHP 8.1 on shared hosting):
 *   - pdo_mysql there returns every column as a string → all DB values are
 *     cast explicitly before use.
 *   - ZipArchive / SimpleXML may be missing → .xlsx gives a clear "upload CSV"
 *     message and the template falls back to CSV.
 *   - sys_get_temp_dir() may be outside open_basedir → a private folder under
 *     uploads/ is used instead.
 */
declare(strict_types=1);

namespace Mori;

final class NavImport
{
    public const MAX_BYTES  = 15 * 1024 * 1024;   // upload size guard
    public const MAX_ROWS   = 200000;             // ~70 years of daily data × 11 classes
    public const MAX_COLS   = 300;                // far more than any price file needs
    public const JUMP_WARN  = 0.15;               // flag NAV moves > 15% between consecutive points
    public const MAX_NAV    = 99999999999.9999;   // DECIMAL(15,4)
    public const ARCHIVE_MAX_BYTES = 5 * 1024 * 1024;  // source files up to 5 MB are archived in the DB
    public const STALE_MSG  = 'Prices were changed by someone else after you reviewed this file. Nothing was published — please review it again.';

    private const NS_REL    = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_MAIN   = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const NS_PKGREL = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /** Header aliases → canonical field. Compared after normaliseHeader(). */
    private const HEADER_ALIASES = [
        'date' => [
            'date', 'nav date', 'valuation date', 'valuation day', 'price date', 'pricing date',
            'as of', 'as of date', 'as at', 'as at date',
            'datum', 'bewertungsdatum', 'stichtag', 'nav datum', 'bewertungstag',
        ],
        'isin' => ['isin', 'isin code', 'isin number', 'isin nr', 'isin no'],
        'class' => [
            'share class', 'share class name', 'class', 'class name', 'name',
            'anteilsklasse', 'anteilklasse',
        ],
        'nav' => [
            'nav', 'nav per share', 'nav share', 'nav per unit', 'net asset value per share',
            'net asset value', 'price', 'nav price', 'unit price', 'share price',
            'anteilwert', 'nettoinventarwert', 'nettoinventarwert je anteil', 'nav je anteil',
            'inventarwert', 'kurs',
        ],
        'benchmark' => ['benchmark', 'benchmark value', 'benchmark index', 'benchmark level', 'vergleichsindex'],
        'currency'  => ['currency', 'ccy', 'currency code', 'curr', 'wahrung', 'waehrung', 'wahrungscode'],
    ];
    private const FIELD_LABELS = [
        'date' => 'Date', 'isin' => 'ISIN', 'class' => 'Share class', 'nav' => 'NAV',
        'benchmark' => 'Benchmark', 'currency' => 'Currency',
    ];
    /** Parenthesised hints that may be dropped from a column title ("Benchmark (optional)"). */
    private const DROPPABLE_HINTS = ['optional', 'opt', 'required', 'eur', 'usd', 'gbp', 'chf', 'ccy', 'in eur', 'in usd', 'in gbp'];
    private const EXCEL_ERRORS = ['#N/A', '#REF!', '#VALUE!', '#DIV/0!', '#NUM!', '#NAME?', '#NULL!', '#SPILL!', '#CALC!', '#GETTING_DATA', '#FORMULA'];

    // ======================================================================
    // 1. READING
    // ======================================================================

    /**
     * Read an uploaded file into rows.
     * Each row: n (sheet/file row number), cells (strings), and for .xlsx also
     * types (cell type per column: n|s|str|inlineStr|b|e|d) and fmt
     * (per column: 'date' | 'percent' when the cell has such a number format).
     * @return array{rows: list<array{n:int, cells:list<string>, types?:array<int,string>, fmt?:array<int,string>}>, source:string, delimiter:?string, date1904:bool}
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

    /** True when this server can read (and so should hand out) .xlsx files. */
    public static function xlsxSupported(): bool
    {
        return class_exists(\ZipArchive::class) && function_exists('simplexml_load_string');
    }

    private static function readCsv(string $path): array
    {
        $raw = (string) file_get_contents($path);

        // Encoding: UTF-16 (Excel "Unicode Text"), UTF-8 BOM, or legacy Windows-1252.
        $mb = function_exists('mb_convert_encoding');
        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $enc = str_starts_with($raw, "\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE';
            if ($mb) {
                $raw = (string) mb_convert_encoding(substr($raw, 2), 'UTF-8', $enc);
            } elseif (function_exists('iconv')) {
                $raw = (string) iconv($enc, 'UTF-8', substr($raw, 2));
            } else {
                throw new \RuntimeException('This text file is saved as "Unicode/UTF-16", which this server cannot read. Please save it as "CSV UTF-8" instead.');
            }
        } elseif (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        if (!preg_match('//u', $raw)) {                      // not valid UTF-8 → assume Windows-1252
            if ($mb) {
                $raw = (string) mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
            } elseif (function_exists('iconv')) {
                $raw = (string) iconv('Windows-1252', 'UTF-8//IGNORE', $raw);
            } else {
                $raw = (string) utf8_encode($raw);
            }
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
        unset($raw);
        rewind($fh);

        $rows = [];
        $n = 0;
        while (($cells = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
            $n++;
            if ($cells === [null]) continue;                      // blank line
            if (count($cells) > self::MAX_COLS) {
                fclose($fh);
                throw new \RuntimeException("Row {$n} has more than " . self::MAX_COLS . ' columns — this does not look like a price file.');
            }
            $cells = array_map(fn($c) => self::cleanCell((string) $c), $cells);
            if (implode('', $cells) === '') continue;
            $rows[] = ['n' => $n, 'cells' => array_values($cells)];
            if (count($rows) > self::MAX_ROWS) {
                fclose($fh);
                throw new \RuntimeException('The file has more than ' . number_format(self::MAX_ROWS) . ' rows.');
            }
        }
        fclose($fh);

        return ['rows' => $rows, 'source' => 'csv', 'delimiter' => $delim, 'date1904' => false];
    }

    /** Pick the delimiter from the header line (first non-empty, non-comment line). */
    private static function detectDelimiter(string $raw): string
    {
        foreach (explode("\n", $raw) as $line) {
            $t = trim($line);
            if ($t === '' || self::isComment($t)) continue;
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

    /** "# …" comment line in a CSV — but never an Excel error value such as #N/A. */
    private static function isComment(string $firstCell): bool
    {
        if (!str_starts_with($firstCell, '#')) return false;
        $u = strtoupper($firstCell);
        foreach (self::EXCEL_ERRORS as $err) {
            if (str_starts_with($u, $err)) return false;
        }
        return true;
    }

    private static function readXlsx(string $path): array
    {
        if (!self::xlsxSupported()) {
            throw new \RuntimeException('Excel files cannot be read on this server. Please save the file as CSV and upload that instead.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('The Excel file could not be opened. Try saving it again, or upload a CSV instead.');
        }

        try {
            $parts  = self::xlsxLocateParts($zip);
            $shared = $parts['shared'] ? self::xlsxSharedStrings($zip, $parts['shared']) : [];
            $styleKinds = $parts['styles'] ? self::xlsxStyleKinds($zip, $parts['styles']) : [];
            $sheet  = self::xlsxLoad($zip, $parts['sheet']);
            if (!$sheet) {
                throw new \RuntimeException('The first worksheet of the Excel file could not be read.');
            }
            $ns = self::xmlNs($sheet);

            $rows = [];
            $auto = 0;
            foreach ($sheet->children($ns)->sheetData->children($ns)->row as $row) {
                $attr = $row->attributes();
                $rn   = isset($attr['r']) ? (int) (string) $attr['r'] : $auto + 1;
                $auto = $rn;

                $cells = $types = $fmt = [];
                $col = 0;
                foreach ($row->children($ns)->c as $c) {
                    $ca  = $c->attributes();
                    $ref = isset($ca['r']) ? (string) $ca['r'] : '';
                    if ($ref !== '' && preg_match('/^([A-Z]+)/i', $ref, $mm)) {
                        $col = self::colIndex(strtoupper($mm[1]));
                    }
                    if ($col > 16383) {
                        throw new \RuntimeException("Row {$rn} refers to a column beyond Excel's last column (XFD) — the file appears to be damaged.");
                    }
                    if ($col >= self::MAX_COLS) {
                        throw new \RuntimeException("Row {$rn} uses more than " . self::MAX_COLS . ' columns — this does not look like a price file.');
                    }
                    $type = isset($ca['t']) ? (string) $ca['t'] : 'n';
                    $kids = $c->children($ns);
                    $hasV = isset($kids->v);
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
                    // A formula whose result was never saved (some generators skip it)
                    // must not be mistaken for an empty cell. A string formula with a
                    // saved empty result (=IF(…;"";…)) IS legitimately empty.
                    if (isset($kids->f) && trim((string) $kids->f) !== '') {
                        if (!$hasV || ($v === '' && $type !== 'str')) {
                            $v = '#FORMULA';
                        }
                    }
                    $cells[$col] = self::cleanCell($v);
                    $types[$col] = $type;
                    $s = isset($ca['s']) ? (int) (string) $ca['s'] : 0;
                    if (isset($styleKinds[$s])) $fmt[$col] = $styleKinds[$s];
                    $col++;
                }
                if (!$cells) continue;
                $max = max(array_keys($cells));
                $dense = [];
                for ($i = 0; $i <= $max; $i++) $dense[] = $cells[$i] ?? '';
                if (implode('', $dense) === '') continue;
                $rows[] = ['n' => $rn, 'cells' => $dense, 'types' => $types, 'fmt' => $fmt];
                if (count($rows) > self::MAX_ROWS) {
                    throw new \RuntimeException('The file has more than ' . number_format(self::MAX_ROWS) . ' rows.');
                }
            }
            return ['rows' => $rows, 'source' => 'xlsx', 'delimiter' => null, 'date1904' => $parts['date1904']];
        } finally {
            $zip->close();
        }
    }

    /** @return array{sheet:string, shared:?string, styles:?string, date1904:bool} */
    private static function xlsxLocateParts(\ZipArchive $zip): array
    {
        $out = [
            'sheet'    => 'xl/worksheets/sheet1.xml',
            'shared'   => $zip->locateName('xl/sharedStrings.xml') !== false ? 'xl/sharedStrings.xml' : null,
            'styles'   => $zip->locateName('xl/styles.xml') !== false ? 'xl/styles.xml' : null,
            'date1904' => false,
        ];

        $wb   = self::xlsxLoad($zip, 'xl/workbook.xml');
        $rels = self::xlsxLoad($zip, 'xl/_rels/workbook.xml.rels');
        if (!$wb) return $out;
        $wbNs = self::xmlNs($wb);

        // Mac "1904 date system": serial 0 = 1904-01-01 instead of 1899-12-30.
        $wbPr = $wb->children($wbNs)->workbookPr;
        if ($wbPr !== null) {
            $d = strtolower((string) ($wbPr->attributes()['date1904'] ?? ''));
            $out['date1904'] = ($d === '1' || $d === 'true');
        }
        if (!$rels) return $out;

        $targets = [];
        foreach ($rels->children(self::xmlNs($rels))->Relationship as $r) {
            $a = $r->attributes();
            $target = (string) ($a['Target'] ?? '');
            $target = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
            $targets[(string) ($a['Id'] ?? '')] = $target;
            $typeAttr = (string) ($a['Type'] ?? '');
            if (str_ends_with($typeAttr, '/sharedStrings')) $out['shared'] = $target;
            if (str_ends_with($typeAttr, '/styles'))        $out['styles'] = $target;
        }

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
                    $out['sheet'] = $targets[$rid];
                }
                break;   // first visible sheet only
            }
        }
        return $out;
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

    /**
     * Map cell style index → 'date' | 'percent' for styles whose number format
     * is a date/time or a percentage (other styles are omitted).
     * @return array<int,string>
     */
    private static function xlsxStyleKinds(\ZipArchive $zip, string $path): array
    {
        $xml = self::xlsxLoad($zip, $path);
        if (!$xml) return [];
        $ns = self::xmlNs($xml);
        $custom = [];
        $numFmts = $xml->children($ns)->numFmts;
        if ($numFmts !== null) {
            foreach ($numFmts->children($ns)->numFmt as $f) {
                $a = $f->attributes();
                $custom[(int) (string) ($a['numFmtId'] ?? -1)] = (string) ($a['formatCode'] ?? '');
            }
        }
        $kinds = [];
        $xfs = $xml->children($ns)->cellXfs;
        if ($xfs === null) return [];
        $i = 0;
        foreach ($xfs->children($ns)->xf as $xf) {
            $id = (int) (string) ($xf->attributes()['numFmtId'] ?? 0);
            $kind = null;
            if (in_array($id, [9, 10], true)) {
                $kind = 'percent';
            } elseif (($id >= 14 && $id <= 22) || ($id >= 45 && $id <= 47) || ($id >= 27 && $id <= 36) || ($id >= 50 && $id <= 58)) {
                $kind = 'date';
            } elseif (isset($custom[$id])) {
                $code = (string) preg_replace(['/"[^"]*"/', '/\[[^\]]*\]/', '/\\\\./', '/_./', '/\*./'], '', $custom[$id]);
                if (str_contains($code, '%'))              $kind = 'percent';
                elseif (preg_match('/[ymdhs]/i', $code))  $kind = 'date';
            }
            if ($kind) $kinds[$i] = $kind;
            $i++;
        }
        return $kinds;
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
        $size = (int) $stat['size'];
        $comp = max(1, (int) $stat['comp_size']);
        if ($size > 40 * 1024 * 1024 || ($size > 10 * 1024 * 1024 && $size / $comp > 250)) {
            throw new \RuntimeException('The Excel file is too large to process. Please upload only the price sheet, or a CSV.');
        }
        $data = $zip->getFromName($name);
        if ($data === false || $data === '') return null;
        // Office Open XML never contains a DTD — refuse entity tricks outright.
        if (stripos($data, '<!DOCTYPE') !== false || stripos($data, '<!ENTITY') !== false) {
            throw new \RuntimeException('The Excel file contains unexpected content and was rejected.');
        }
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
        if (strlen($letters) > 3) return PHP_INT_MAX;   // beyond XFD
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
     * Split a currency code/symbol off a number. Case-sensitive on purpose:
     * "GBp"/"GBX" (pence) must never be mistaken for "GBP" (pounds).
     * @return array{0:string, 1:?string} [number part, currency: EUR|USD|GBP|CHF|GBX|null]
     */
    public static function stripCurrency(string $s): array
    {
        $s = self::cleanCell($s);
        $map = ['€' => 'EUR', '$' => 'USD', '£' => 'GBP', 'GBp' => 'GBX', 'GBx' => 'GBX', 'GBX' => 'GBX'];
        $re  = '(EUR|USD|GBP|CHF|GBp|GBx|GBX|€|\$|£)';
        $ccy = null;
        if (preg_match('/^' . $re . '\s*(.*)$/u', $s, $m)) {
            $ccy = $map[$m[1]] ?? $m[1]; $s = $m[2];
        } elseif (preg_match('/^(.*?)\s*' . $re . '$/u', $s, $m)) {
            $ccy = $map[$m[2]] ?? $m[2]; $s = $m[1];
        }
        return [$s, $ccy];
    }

    /** "1,235" / "1.235": one separator followed by exactly three digits — thousands or decimals? */
    public static function isAmbiguousNumber(string $s): bool
    {
        $s = str_replace([' ', "'", '’'], '', self::stripCurrency($s)[0]);
        return (bool) preg_match('/^[+-]?\d{1,3}[.,]\d{3}$/', $s);
    }

    /**
     * Parse a price. Accepts 142.86 · 142,86 · 1,234.56 · 1.234,56 · 1 234,56 ·
     * 1'234.56 · "EUR 142.86" · 1.4286E2.
     * @param ?string  $decimal  '.' or ',' — the file's decimal separator, used only
     *                           to resolve "1,235"-style values (null = treat as decimal)
     * @param ?string  $currency out: currency code found in the cell (EUR|USD|GBP|CHF|GBX)
     * @return float|null|false  null = empty cell, false = not a number
     */
    public static function parseNumber(string $s, ?string $decimal = null, ?string &$currency = null): float|null|false
    {
        $currency = null;
        $s = self::cleanCell($s);
        if ($s === '' || $s === '-' || $s === '—') return null;

        [$s, $currency] = self::stripCurrency($s);
        $s = str_replace([' ', "'", '’'], '', $s);

        if (preg_match('/^[+-]?\d+(\.\d+)?(e[+-]?\d+)?$/i', $s)) {
            if ($decimal === ',' && preg_match('/^[+-]?\d{1,3}\.\d{3}$/', $s)) {
                return (float) str_replace('.', '', $s);          // 1.235 in a comma-decimal file
            }
            return (float) $s;                                    // 142.86 · 1.4286E2
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
            if ($decimal === '.' && preg_match('/^[+-]?\d{1,3},\d{3}$/', $s)) {
                return (float) str_replace(',', '', $s);          // 1,235 in a point-decimal file
            }
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
     * @param bool   $date1904   Excel serials use the Mac 1904 date system
     * @return string|null|false null = empty, false = not a valid date
     */
    public static function parseDate(string $s, string $slashOrder = 'dmy', bool $date1904 = false): string|null|false
    {
        $s = self::cleanCell($s);
        if ($s === '') return null;

        // Excel serial date (e.g. 46298 or 46298.5). Range ≈ 1954 … 2119.
        if (preg_match('/^\d{5}(\.\d+)?$/', $s)) {
            $serial = (int) floor((float) $s) + ($date1904 ? 1462 : 0);
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
            $t = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
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

    private static function lower(string $s): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
    }

    /** Lowercase, fold umlauts, drop only harmless "(…)" hints, collapse punctuation. */
    private static function normaliseHeader(string $h): string
    {
        $h = self::lower(self::cleanCell($h));
        $h = strtr($h, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'Ä' => 'a', 'Ö' => 'o', 'Ü' => 'u']);
        // "Benchmark (optional)" → "benchmark"; but "NAV (previous day)" keeps its words
        $h = (string) preg_replace_callback('/\(([^)]*)\)/', function ($m) {
            $inner = trim((string) preg_replace('/[^a-z0-9]+/', ' ', $m[1]));
            return in_array($inner, self::DROPPABLE_HINTS, true) ? ' ' : ' ' . $inner . ' ';
        }, $h);
        $h = (string) preg_replace('/[^a-z0-9]+/', ' ', $h);
        return trim((string) preg_replace('/\s+/', ' ', $h));
    }

    private static function canonicalField(string $norm): ?string
    {
        foreach (self::HEADER_ALIASES as $canon => $aliases) {
            if (in_array($norm, $aliases, true)) return $canon;
        }
        return null;
    }

    private static function normaliseName(string $n): string
    {
        $n = self::lower(self::cleanCell($n));
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

    private static function dotDecimalEvidence(string $v): bool
    {
        return (bool) (preg_match('/^[+-]?\d+\.\d{1,2}$/', $v) || preg_match('/^[+-]?\d+\.\d{4,}$/', $v)
            || preg_match('/\d,\d{3}\.\d/', $v) || preg_match('/^[+-]?\d{4,}\.\d+$/', $v));
    }

    private static function commaDecimalEvidence(string $v): bool
    {
        return (bool) (preg_match('/^[+-]?\d+,\d{1,2}$/', $v) || preg_match('/^[+-]?\d+,\d{4,}$/', $v)
            || preg_match('/\d\.\d{3},\d/', $v) || preg_match('/^[+-]?\d{4,},\d+$/', $v));
    }

    // ======================================================================
    // 3. ANALYSIS (pure — no database)
    // ======================================================================

    /**
     * Validate rows against the known share classes.
     *
     * @param list<array{n:int, cells:list<string>, types?:array<int,string>, fmt?:array<int,string>}> $rows
     * @param list<array<string,mixed>> $classes  share_classes rows (id, name, isin, currency)
     * @param string $today  Y-m-d (server date) — later dates are rejected
     * @param array{source?:string, delimiter?:?string, date1904?:bool} $opts  readFile() metadata
     * @return array{
     *   format: string, entries: list<array{sc:int, date:string, nav:float, bench:?float, row:int}>,
     *   errors: list<string>, warnings: list<string>, notes: list<string>,
     *   skipped_blank: int, has_benchmark: bool
     * }
     */
    public static function analyze(array $rows, array $classes, string $today, array $opts = []): array
    {
        $source   = (string) ($opts['source'] ?? 'csv');
        $date1904 = !empty($opts['date1904']);
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

        // Drop "# …" comment lines (CSV only — in Excel a leading "#" is an error value like #N/A)
        if ($source !== 'xlsx') {
            $rows = array_values(array_filter($rows, fn($r) => !self::isComment((string) ($r['cells'][0] ?? ''))));
        }
        if (!$rows) {
            $res['errors'][] = 'The file contains no data.';
            return $res;
        }

        // ---- Header -------------------------------------------------------
        $header = $rows[0];
        $dataRows = array_slice($rows, 1);
        $field = $fieldTitle = $unknownCols = $titled = [];
        foreach ($header['cells'] as $i => $h) {
            $norm = self::normaliseHeader($h);
            if ($norm === '') continue;
            $titled[$i] = true;
            $canon = self::canonicalField($norm);
            if ($canon === null) { $unknownCols[$i] = $h; continue; }
            if (isset($field[$canon])) {
                $res['errors'][] = sprintf(
                    'Columns "%s" and "%s" could both be the %s column. Delete or rename the one that should not be used, then upload again.',
                    $fieldTitle[$canon], $h, self::FIELD_LABELS[$canon]
                );
                continue;
            }
            $field[$canon] = $i;
            $fieldTitle[$canon] = $h;
        }
        if ($res['errors']) return $res;

        if (!isset($field['date'])) {
            $res['errors'][] = 'No "Date" column found in the first row. The first row must contain the column titles (e.g. Date, ISIN, NAV) — please use the template.';
            return $res;
        }

        // ---- Values outside the titled columns ------------------------------
        // In a comma-separated file an unquoted "512,4568" becomes two cells; the
        // extra cell shows up beyond (or under an empty) column title.
        $width = count($header['cells']);
        $stray = [];
        foreach ($dataRows as $r) {
            foreach ($r['cells'] as $i => $v) {
                if ($v !== '' && ($i >= $width || empty($titled[$i]))) { $stray[] = (int) $r['n']; break; }
            }
        }
        if ($stray) {
            $list = implode(', ', array_slice($stray, 0, 8)) . (count($stray) > 8 ? ' …' : '');
            if ($source === 'xlsx') {
                $res['notes'][] = "Values in columns without a title were ignored (row {$list}).";
            } else {
                $res['errors'][] = "Row {$list}: there are more values than column titles. This usually means a number was written with a comma (e.g. 512,4568) in a comma-separated file. Put such numbers in quotes, save the file with semicolons, or upload the Excel file instead.";
                return $res;
            }
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
                    // A share-class name next to the ISIN must agree with it.
                    $rest = self::normaliseName((string) str_ireplace($mm[1], ' ', $h));
                    if ($rest !== '' && isset($byName[$rest]) && $byName[$rest] !== $id) {
                        $res['errors'][] = 'Column "' . $h . '": the name is ' . $byId[$byName[$rest]]['name'] . ' but ISIN ' . $mm[1] . ' belongs to ' . $byId[$id]['name'] . '.';
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
        if ($date1904) {
            $res['notes'][] = 'This workbook uses the Mac "1904 date system"; dates were converted accordingly.';
        }

        // ---- Decimal separator of the file (only matters for "1,235"-style values)
        $numCols = $res['format'] === 'long'
            ? array_values(array_filter([$field['nav'], $field['benchmark'] ?? null], fn($x) => $x !== null))
            : array_keys($wideCols);
        $dotEv = $commaEv = null;
        foreach ($dataRows as $r) {
            foreach ($numCols as $ci) {
                if ($source === 'xlsx' && ($r['types'][$ci] ?? '') === 'n') continue;   // real numbers: unambiguous
                $v = str_replace([' ', "'", '’'], '', self::stripCurrency((string) ($r['cells'][$ci] ?? ''))[0]);
                if ($v === '') continue;
                if (self::dotDecimalEvidence($v))   $dotEv   ??= $v;
                if (self::commaDecimalEvidence($v)) $commaEv ??= $v;
            }
        }
        $decimal = null;
        if ($dotEv !== null && $commaEv === null)      $decimal = '.';
        elseif ($commaEv !== null && $dotEv === null)  $decimal = ',';
        elseif ($dotEv === null && $commaEv === null) {
            $d = (string) ($opts['delimiter'] ?? '');
            $decimal = $d === ';' ? ',' : ($d === ',' ? '.' : null);
        }

        // ---- Rows -----------------------------------------------------------
        $entries = [];             // "sc|date" => entry
        $errors  = [];
        $decimalsNote = false;
        $addError = function (string $msg) use (&$errors) { $errors[] = $msg; };

        /** Parse a NAV / benchmark cell; returns rounded value, null (empty) or false (error reported). */
        $parseValue = function (array $r, int $ci, string $who, int $sc, string $what) use ($source, $decimal, $addError, &$decimalsNote, $byId): float|null|false {
            $raw  = (string) ($r['cells'][$ci] ?? '');
            $rowN = (int) $r['n'];
            $kind = $r['fmt'][$ci] ?? null;
            if ($raw === '') return null;
            if ($raw === '#FORMULA') {
                $addError("Row {$rowN}: the {$what} for {$who} is a formula without a saved result — open the file in Excel, save it and upload it again (or paste the values as numbers).");
                return false;
            }
            if (in_array(strtoupper($raw), self::EXCEL_ERRORS, true)) {
                $addError("Row {$rowN}: the {$what} for {$who} is an Excel error ({$raw}).");
                return false;
            }
            if ($kind === 'date' || $kind === 'percent') {
                $addError("Row {$rowN}: the {$what} cell for {$who} is formatted as a " . ($kind === 'date' ? 'date' : 'percentage') . " in Excel. Format the column as a number and enter the {$what} again.");
                return false;
            }
            $ccy = null;
            if ($source === 'xlsx' && ($r['types'][$ci] ?? '') === 'n') {
                if (!is_numeric($raw)) { $addError("Row {$rowN}: {$what} \"{$raw}\" for {$who} is not a number."); return false; }
                $val = (float) $raw;
            } else {
                if ($decimal === null && self::isAmbiguousNumber($raw)) {
                    $addError("Row {$rowN}: {$what} \"{$raw}\" for {$who} is ambiguous (thousands or decimals?). Write it with all decimals, e.g. 1235.0000 or 1.2350.");
                    return false;
                }
                $val = self::parseNumber($raw, $decimal, $ccy);
                if ($val === null) return null;
                if ($val === false) { $addError("Row {$rowN}: {$what} \"{$raw}\" for {$who} is not a number."); return false; }
            }
            if ($ccy === 'GBX') {
                $addError("Row {$rowN}: the {$what} for {$who} is given in pence (GBp/GBX). Enter prices in pounds (GBP).");
                return false;
            }
            if ($ccy !== null && $what === 'NAV' && $ccy !== strtoupper((string) $byId[$sc]['currency'])) {
                $addError("Row {$rowN}: the NAV for {$who} is marked {$ccy}, but this share class is priced in {$byId[$sc]['currency']}.");
                return false;
            }
            $rounded = round($val, 4);
            if ($what === 'NAV' && $rounded <= 0) { $addError("Row {$rowN}: NAV {$raw} for {$who} must be greater than zero."); return false; }
            if (abs($rounded) > self::MAX_NAV)    { $addError("Row {$rowN}: {$what} {$raw} for {$who} is too large."); return false; }
            if (abs($val - $rounded) > 1e-9) $decimalsNote = true;
            return $rounded;
        };
        $checkDate = function (string $raw, int $rowN) use ($slashOrder, $date1904, $today, $addError): string|null|false {
            if (in_array(strtoupper($raw), self::EXCEL_ERRORS, true)) { $addError("Row {$rowN}: the date is an Excel error ({$raw})."); return false; }
            $d = self::parseDate($raw, $slashOrder, $date1904);
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
                        if ($rawNav === '') { $res['skipped_blank']++; continue; }
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
                        if ($rawNav === '') { $res['skipped_blank']++; continue; }
                        $addError("Row {$n}: share class \"{$rawName}\" not recognised — add the ISIN column to be safe.");
                        continue;
                    }
                } else {
                    if ($rawNav === '') { $res['skipped_blank']++; continue; }
                    $addError("Row {$n}: no ISIN / share class given.");
                    continue;
                }

                $nav = $parseValue($r, $field['nav'], $label($sc), $sc, 'NAV');
                if ($nav === null) { $res['skipped_blank']++; continue; }
                if ($nav === false) continue;

                $date = $checkDate($rawDate, $n);
                if ($date === null) { $addError("Row {$n}: date is missing for {$label($sc)}."); continue; }
                if ($date === false) continue;

                if (isset($field['currency'])) {
                    $rawCcy = $get($field['currency']);
                    if (preg_match('/^(GBp|GBx|GBX|pence|p)$/', $rawCcy)) {
                        $addError("Row {$n}: currency {$rawCcy} means pence — enter the NAV of {$label($sc)} in pounds (GBP).");
                        continue;
                    }
                    $ccy = strtoupper($rawCcy);
                    if ($ccy !== '' && $ccy !== strtoupper((string) $byId[$sc]['currency'])) {
                        $addError("Row {$n}: currency {$ccy} does not match {$label($sc)}, which is priced in {$byId[$sc]['currency']}.");
                        continue;
                    }
                }

                $bench = null;
                if (isset($field['benchmark'])) {
                    $b = $parseValue($r, $field['benchmark'], $label($sc), $sc, 'benchmark');
                    if ($b === false) continue;
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
                    $nav = $parseValue($r, $ci, $label($sc), $sc, 'NAV');
                    if ($nav === null || $nav === false) continue;
                    $put($sc, $date, $nav, null, $n);
                }
            }
        }

        if ($decimalsNote) {
            $res['notes'][] = 'Some values have more than 4 decimal places — they are stored rounded to 4 decimals.';
        }
        $res['errors'] = $errors;

        $list = array_values($entries);
        usort($list, fn($a, $b) => [$a['sc'], $a['date']] <=> [$b['sc'], $b['date']]);
        $res['entries'] = $list;

        if (!$errors && !$list) {
            $res['errors'][] = 'No prices found in the file — every NAV cell is empty.';
        }

        // Date plausibility (prices are normally struck for the previous business day)
        $dates = array_values(array_unique(array_column($list, 'date')));
        if (in_array($today, $dates, true)) {
            $res['notes'][] = 'Some prices are dated today (' . $today . '). If they are the previous business day\'s NAVs, correct the date before publishing.';
        }
        $weekend = array_values(array_filter($dates, fn($d) => (int) (new \DateTimeImmutable($d))->format('N') >= 6));
        if ($weekend) {
            $res['warnings'][] = 'Prices dated on a weekend: ' . implode(', ', array_slice($weekend, 0, 6)) . (count($weekend) > 6 ? ' …' : '') . ' — please check the dates.';
        }
        return $res;
    }

    /**
     * Flag suspicious NAV moves (typos, wrong decimal separator, wrong class) —
     * in BOTH directions: against the previously published price, against a
     * later published price (back-filled history), and between file dates.
     *
     * @param array<int, array<string,float>> $stored  share class id => [date => published NAV]
     *        covering the file's date range plus the published neighbour on each side
     * @param array<int,string> $names  share class id => display label
     * @return list<string>
     */
    public static function seriesWarnings(array $entries, array $stored, array $names): array
    {
        $out = [];
        $bySc = [];
        foreach ($entries as $e) $bySc[$e['sc']][] = $e;
        foreach ($bySc as $sc => $list) {
            $pts = [];
            foreach ($stored[$sc] ?? [] as $d => $v) $pts[(string) $d] = ['v' => (float) $v, 'pub' => true];
            foreach ($list as $e) $pts[$e['date']] = ['v' => (float) $e['nav'], 'pub' => false];
            ksort($pts);
            $prev = null; $prevDate = null; $count = 0;
            foreach ($pts as $d => $p) {
                if ($prev !== null && (!$prev['pub'] || !$p['pub']) && $prev['v'] > 0) {
                    $chg = $p['v'] / $prev['v'] - 1;
                    if (abs($chg) > self::JUMP_WARN) {
                        if ($count < 3) {
                            $out[] = sprintf(
                                '%s: NAV %s on %s%s → %s on %s%s (%+.1f%%) — please double-check (decimal separator, share class, currency).',
                                $names[$sc] ?? ('#' . $sc),
                                self::fmt($prev['v']), $prevDate, $prev['pub'] ? ' (published)' : '',
                                self::fmt($p['v']), $d, $p['pub'] ? ' (published)' : '',
                                $chg * 100
                            );
                        }
                        $count++;
                    }
                }
                $prev = $p;
                $prevDate = (string) $d;
            }
            if ($count > 3) {
                $out[] = ($names[$sc] ?? ('#' . $sc)) . ': ' . ($count - 3) . ' more large moves not listed.';
            }
        }
        return $out;
    }

    private static function fmt(float $v): string
    {
        return number_format($v, 4, '.', ',');
    }

    // ======================================================================
    // 4. DATABASE: review diff, fingerprint, apply
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
     * Published NAVs around the file's dates (for seriesWarnings):
     * everything between the first and last file date, plus the nearest
     * published price before and after.
     * @return array<int, array<string,float>>
     */
    public static function storedWindow(Database $db, array $entries): array
    {
        $bySc = [];
        foreach ($entries as $e) $bySc[$e['sc']][] = $e['date'];
        $out = [];
        foreach ($bySc as $sc => $dates) {
            $first = min($dates); $last = max($dates);
            $rows = $db->fetchAll('SELECT entry_date, nav FROM nav_entries WHERE share_class_id = :s AND entry_date BETWEEN :a AND :b',
                ['s' => $sc, 'a' => $first, 'b' => $last]);
            foreach ([
                ['SELECT entry_date, nav FROM nav_entries WHERE share_class_id = :s AND entry_date < :d ORDER BY entry_date DESC LIMIT 1', $first],
                ['SELECT entry_date, nav FROM nav_entries WHERE share_class_id = :s AND entry_date > :d ORDER BY entry_date ASC LIMIT 1', $last],
            ] as [$sql, $d]) {
                $row = $db->fetchOne($sql, ['s' => $sc, 'd' => $d]);
                if ($row) $rows[] = $row;
            }
            foreach ($rows as $row) $out[(int) $sc][(string) $row['entry_date']] = (float) $row['nav'];
        }
        return $out;
    }

    /**
     * Compare file entries with what is published.
     * @return array<int, array{rows:int, first:string, last:string, last_nav:float, last_bench:?float,
     *   new:int, changed:int, unchanged:int, existing_total:int,
     *   prev_nav:?float, prev_date:?string, next_nav:?float, next_date:?string,
     *   overwrites: list<array{date:string, old_nav:float, old_bench:?float, new_nav:float, new_bench:?float}>}>
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
                'SELECT entry_date, nav, benchmark_value FROM nav_entries WHERE share_class_id = :s AND entry_date BETWEEN :a AND :b',
                ['s' => $sc, 'a' => $first, 'b' => $last]
            ) as $row) {
                $stored[(string) $row['entry_date']] = [
                    (float) $row['nav'],
                    $row['benchmark_value'] === null ? null : (float) $row['benchmark_value'],
                ];
            }
            $new = $changed = $unchanged = 0;
            $overwrites = [];
            foreach ($list as $e) {
                if (!array_key_exists($e['date'], $stored)) { $new++; continue; }
                [$oldNav, $oldBench] = $stored[$e['date']];
                $navDiff   = abs($oldNav - round($e['nav'], 4)) >= 0.00005;
                $benchDiff = $e['bench'] !== null && ($oldBench === null || abs($oldBench - round($e['bench'], 4)) >= 0.00005);
                if ($navDiff || $benchDiff) {
                    $changed++;
                    $overwrites[] = ['date' => $e['date'], 'old_nav' => $oldNav, 'old_bench' => $oldBench, 'new_nav' => (float) $e['nav'], 'new_bench' => $e['bench']];
                } else {
                    $unchanged++;
                }
            }
            $prev = $db->fetchOne(
                'SELECT entry_date, nav FROM nav_entries WHERE share_class_id = :s AND entry_date < :d ORDER BY entry_date DESC LIMIT 1',
                ['s' => $sc, 'd' => $first]
            );
            $next = $db->fetchOne(
                'SELECT entry_date, nav FROM nav_entries WHERE share_class_id = :s AND entry_date > :d ORDER BY entry_date ASC LIMIT 1',
                ['s' => $sc, 'd' => $last]
            );
            $lastEntry = $list[count($list) - 1];
            $out[(int) $sc] = [
                'rows' => count($list), 'first' => $first, 'last' => $last,
                'last_nav' => (float) $lastEntry['nav'], 'last_bench' => $lastEntry['bench'],
                'new' => $new, 'changed' => $changed, 'unchanged' => $unchanged,
                'existing_total' => (int) $db->fetchColumn('SELECT COUNT(*) FROM nav_entries WHERE share_class_id = :s', ['s' => $sc]),
                'prev_nav'  => $prev ? (float) $prev['nav'] : null,
                'prev_date' => $prev ? (string) $prev['entry_date'] : null,
                'next_nav'  => $next ? (float) $next['nav'] : null,
                'next_date' => $next ? (string) $next['entry_date'] : null,
                'overwrites' => $overwrites,
            ];
        }
        return $out;
    }

    /**
     * Fingerprint of the stored prices of the given share classes. Taken when the
     * review is shown and checked again (under row locks) when publishing, so a
     * concurrent change by someone else aborts the publish instead of being
     * silently overwritten or deleted.
     */
    public static function fingerprint(Database $db, array $classIds, bool $lock = false): string
    {
        $ids = array_values(array_unique(array_map('intval', $classIds)));
        sort($ids);
        if (!$ids) return '';
        $in = implode(',', $ids);
        if ($lock) {
            $db->pdo()->query("SELECT id FROM nav_entries WHERE share_class_id IN ({$in}) FOR UPDATE")->fetchAll();
        }
        // Order-independent per-row checksum: catches any changed, added or removed row
        $rows = $db->fetchAll(
            "SELECT share_class_id, COUNT(*) AS c, MIN(entry_date) AS f, MAX(entry_date) AS m,
                    SUM(CRC32(CONCAT_WS('|', entry_date, nav, COALESCE(benchmark_value, '-')))) AS h
               FROM nav_entries WHERE share_class_id IN ({$in}) GROUP BY share_class_id ORDER BY share_class_id"
        );
        $norm = array_map(fn($r) => [
            (int) $r['share_class_id'], (int) $r['c'], (string) $r['f'], (string) $r['m'], (string) $r['h'],
        ], $rows);
        return hash('sha256', $in . '|' . json_encode($norm));
    }

    /**
     * Write entries in one transaction.
     *   upsert  — insert new dates, overwrite changed NAVs (default)
     *   add     — insert new dates only, keep existing values
     *   replace — delete ALL stored NAVs of the share classes in the file first
     * A blank/absent benchmark never wipes a stored benchmark value.
     *
     * $ctx: expected_fingerprint (abort if stored prices changed since review),
     *       user_id, file_name, file_path (archived with the previous values in
     *       nav_import_log so every publish is traceable and reversible).
     *
     * @return array{inserted:int, updated:int, unchanged:int, deleted:int, log_id:?int,
     *               classes: array<int, array{first:string,last:string,inserted:int,updated:int,unchanged:int,deleted:int}>}
     */
    public static function apply(Database $db, array $entries, string $mode, array $ctx = []): array
    {
        if (!in_array($mode, ['upsert', 'add', 'replace'], true)) {
            throw new \InvalidArgumentException('Unknown import mode.');
        }
        return $db->transaction(function (Database $db) use ($entries, $mode, $ctx): array {
            $pdo = $db->pdo();
            $ids = array_values(array_unique(array_map(fn($e) => (int) $e['sc'], $entries)));
            sort($ids);

            if (isset($ctx['expected_fingerprint'])) {
                $now = self::fingerprint($db, $ids, true);
                if (!hash_equals((string) $ctx['expected_fingerprint'], $now)) {
                    throw new \RuntimeException(self::STALE_MSG);
                }
            }

            // Capture what is about to be overwritten / deleted (for the import log)
            $before = [];            // sc => [date => [nav, bench]]
            if ($ids) {
                $in = implode(',', $ids);
                if ($mode === 'replace') {
                    $rows = $db->fetchAll("SELECT share_class_id, entry_date, nav, benchmark_value FROM nav_entries WHERE share_class_id IN ({$in})");
                } else {
                    $dates = array_column($entries, 'date');
                    $rows = $db->fetchAll(
                        "SELECT share_class_id, entry_date, nav, benchmark_value FROM nav_entries
                          WHERE share_class_id IN ({$in}) AND entry_date BETWEEN :a AND :b",
                        ['a' => min($dates), 'b' => max($dates)]
                    );
                }
                foreach ($rows as $r) {
                    $before[(int) $r['share_class_id']][(string) $r['entry_date']] = [
                        (string) $r['nav'], $r['benchmark_value'] === null ? null : (string) $r['benchmark_value'],
                    ];
                }
            }

            $classes = [];
            $deleted = 0;
            if ($mode === 'replace' && $ids) {
                foreach ($ids as $id) {
                    $classes[$id] = ['first' => '', 'last' => '', 'inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'deleted' => count($before[$id] ?? [])];
                }
                $deleted = (int) $pdo->exec('DELETE FROM nav_entries WHERE share_class_id IN (' . implode(',', $ids) . ')');
            }
            $sql = 'INSERT INTO nav_entries (share_class_id, entry_date, nav, benchmark_value) VALUES (:s, :d, :n, :b) ';
            $sql .= $mode === 'add'
                ? 'ON DUPLICATE KEY UPDATE id = id'
                : 'ON DUPLICATE KEY UPDATE nav = VALUES(nav), benchmark_value = COALESCE(VALUES(benchmark_value), benchmark_value)';
            $st = $pdo->prepare($sql);

            $ins = $upd = $same = 0;
            $changes = [];           // sc => list of [date, old_nav, old_bench, new_nav, new_bench]
            foreach ($entries as $e) {
                $sc = (int) $e['sc'];
                $classes[$sc] ??= ['first' => $e['date'], 'last' => $e['date'], 'inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0];
                if ($classes[$sc]['first'] === '' || $e['date'] < $classes[$sc]['first']) $classes[$sc]['first'] = $e['date'];
                if ($e['date'] > $classes[$sc]['last']) $classes[$sc]['last'] = $e['date'];
                $nav = sprintf('%.4F', round((float) $e['nav'], 4));
                $bench = $e['bench'] === null ? null : sprintf('%.4F', round((float) $e['bench'], 4));
                $st->execute(['s' => $sc, 'd' => (string) $e['date'], 'n' => $nav, 'b' => $bench]);
                $rc = $st->rowCount();
                if ($rc === 1) { $ins++; $classes[$sc]['inserted']++; }
                elseif ($rc === 2) {
                    $upd++; $classes[$sc]['updated']++;
                    $old = $before[$sc][$e['date']] ?? [null, null];
                    $changes[$sc][] = [$e['date'], $old[0], $old[1], $nav, $bench];
                }
                else { $same++; $classes[$sc]['unchanged']++; }
            }

            $logId = self::writeLog($db, $mode, $ctx, $classes, $changes, $mode === 'replace' ? $before : []);

            return ['inserted' => $ins, 'updated' => $upd, 'unchanged' => $same, 'deleted' => $deleted,
                    'log_id' => $logId, 'classes' => $classes];
        });
    }

    /** Record the publish (with previous values and the source file) — never blocks publishing. */
    private static function writeLog(Database $db, string $mode, array $ctx, array $classes, array $changes, array $deletedRows): ?int
    {
        try {
            $exists = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nav_import_log'"
            );
            if (!$exists) return null;
        } catch (\Throwable) {
            return null;
        }
        $path = (string) ($ctx['file_path'] ?? '');
        $content = ($path !== '' && is_readable($path)) ? (string) file_get_contents($path) : '';
        $payload = [];
        $tot = ['inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0];
        foreach ($classes as $sc => $c) {
            foreach ($tot as $k => $_) $tot[$k] += (int) $c[$k];
            $payload[(string) $sc] = $c + [
                'overwritten' => $changes[$sc] ?? [],
                'deleted_rows' => isset($deletedRows[$sc])
                    ? array_map(fn($d, $v) => [$d, $v[0], $v[1]], array_keys($deletedRows[$sc]), $deletedRows[$sc])
                    : [],
            ];
        }
        $n = count($classes);
        $summary = sprintf('%d share class%s · %d new · %d updated · %d unchanged',
            $n, $n === 1 ? '' : 'es', $tot['inserted'], $tot['updated'], $tot['unchanged'])
            . ($tot['deleted'] ? ' · ' . $tot['deleted'] . ' deleted first' : '');
        $name = (string) ($ctx['file_name'] ?? '');
        $row = [
            'user_id'      => isset($ctx['user_id']) ? (int) $ctx['user_id'] : null,
            'file_name'    => function_exists('mb_substr') ? mb_substr($name, 0, 255) : substr($name, 0, 255),
            'file_sha256'  => $content !== '' ? hash('sha256', $content) : str_repeat('0', 64),
            // base64 text: binary-safe whatever the connection charset / driver
            'file_base64'  => ($content !== '' && strlen($content) <= self::ARCHIVE_MAX_BYTES) ? base64_encode($content) : null,
            'mode'         => $mode,
            'summary'      => $summary,
            'changes_json' => json_encode($payload, JSON_UNESCAPED_SLASHES) ?: null,
        ];
        // Fall back to smaller records if the server refuses a large one (max_allowed_packet)
        $attempts = [$row, ['file_base64' => null] + $row, ['file_base64' => null, 'changes_json' => null] + $row];
        foreach ($attempts as $attempt) {
            try {
                return $db->insert('nav_import_log', $attempt);
            } catch (\Throwable $e) {
                error_log('nav_import_log insert failed: ' . $e->getMessage());
            }
        }
        return null;
    }

    /** Recent publishes for the admin screen (empty when the log table does not exist yet). */
    public static function recentImports(Database $db, int $limit = 8): array
    {
        $limit = max(1, min($limit, 100));
        try {
            return $db->fetchAll(
                'SELECT l.id, l.created_at, l.file_name, l.mode, l.summary, (l.file_base64 IS NOT NULL) AS has_file, u.name AS user_name
                   FROM nav_import_log l LEFT JOIN users u ON u.id = l.user_id
                  ORDER BY l.id DESC LIMIT ' . $limit
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** Archived source file of a publish, or null. @return array{name:string, content:string}|null */
    public static function importFile(Database $db, int $id): ?array
    {
        try {
            $r = $db->fetchOne('SELECT file_name, file_base64 FROM nav_import_log WHERE id = :id', ['id' => $id]);
        } catch (\Throwable) {
            return null;
        }
        if (!$r || $r['file_base64'] === null) return null;
        $content = base64_decode((string) $r['file_base64'], true);
        return $content === false ? null : ['name' => (string) $r['file_name'], 'content' => $content];
    }

    // ======================================================================
    // 5. TEMPORARY STORAGE between "check file" and "publish"
    // ======================================================================

    private static function stashDir(): string
    {
        $candidates = [rtrim(sys_get_temp_dir(), '/\\') . '/mori-nav-import', dirname(__DIR__) . '/uploads/.nav-import'];
        foreach ($candidates as $dir) {
            if (!@is_dir($dir)) @mkdir($dir, 0700, true);
            if (@is_dir($dir) && @is_writable($dir)) {
                if (str_contains($dir, '/uploads/') && !is_file($dir . '/.htaccess')) {
                    @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
                    @file_put_contents($dir . '/index.html', '');
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

    /**
     * The previous business day (NAVs are normally available the morning after
     * the valuation day): yesterday, or Friday when today is Sat/Sun/Mon.
     */
    public static function previousBusinessDay(?string $today = null): string
    {
        $d = (new \DateTimeImmutable($today ?? 'today'))->modify('-1 day');
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
        $date   = self::previousBusinessDay();
        [$header, $rows] = self::templateData($classes, $layout, $date);
        $base = $layout === 'wide' ? 'mori-nav-history-template' : 'mori-nav-prices-template';

        if ($format === 'xlsx' && self::xlsxSupported()) {
            $tmp = null;
            try {
                $tmp = self::stashDir() . '/tpl-' . bin2hex(random_bytes(8)) . '.xlsx';
                self::writeXlsx($tmp, $header, $rows, self::instructions($layout));
                $size = filesize($tmp);
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $base . '.xlsx"');
                if ($size) header('Content-Length: ' . $size);
                header('Cache-Control: no-store');
                readfile($tmp);
                @unlink($tmp);
                exit;
            } catch (\Throwable $e) {
                if ($tmp) @unlink($tmp);
                error_log('NAV xlsx template failed, serving CSV: ' . $e->getMessage());
            }
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
            'DATE: the template is pre-filled with the previous business day. Make sure it is the NAV (valuation)',
            'date of the prices you enter — change it if not. Never re-use an old file without updating the date.',
            '',
        ];
        $specific = $layout === 'wide' ? [
            'One row per date, one column per share class (ideal for loading a price history).',
            'Column titles must contain the ISIN — do not rename them.',
            'Leave a cell empty if there is no price for that share class on that date.',
            'Date: a real Excel date or YYYY-MM-DD (e.g. 2026-10-02). Add as many rows as you like.',
        ] : [
            'One row per share class per date. The 11 share classes are pre-filled.',
            '1. Check the Date (a real Excel date or YYYY-MM-DD, e.g. 2026-10-02).',
            '2. Type each NAV per share in the NAV column (e.g. 142.8634), in the share class currency (pounds, not pence).',
            '3. Rows with an empty NAV are skipped — so you can publish only some share classes.',
            'To add several dates in one go, copy the 11 rows below and change the date.',
            'Do not change the ISIN column — it is how prices are matched to share classes.',
            'Benchmark is optional; leaving it empty never deletes an existing benchmark value.',
        ];
        return array_merge($common, $specific, [
            '',
            'Before anything is published you will see a summary to review (new / changed prices,',
            'unusual moves, prices that would overwrite published ones). Nothing is saved until you click "Publish prices".',
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
            $w = max(12, min(48, strlen($h) + 4));
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
        if (!$zip->close()) {
            throw new \RuntimeException('Could not write the template file.');
        }
    }
}
