<?php

declare(strict_types=1);

namespace App\Helpers;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use ZipArchive;

final class Spreadsheet
{
    /**
     * @param list<array{name: string, rows: list<list<string>>}> $sheets
     */
    public static function xlsx(array $sheets): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Excel support is not enabled on this server.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'lssti-xlsx');
        if ($tmp === false) {
            throw new RuntimeException('Could not create the Excel file.');
        }
        unlink($tmp);
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Could not create the Excel file.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes(count($sheets)));
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels(count($sheets)));
        $zip->addFromString('xl/styles.xml', self::styles());
        foreach ($sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($index + 1) . '.xml', self::worksheet($sheet['rows']));
        }
        $zip->close();
        $binary = file_get_contents($tmp);
        unlink($tmp);
        if ($binary === false || $binary === '') {
            throw new RuntimeException('Could not create the Excel file.');
        }
        return $binary;
    }

    /**
     * @return list<array{row: int, cells: list<string>}>
     */
    public static function readRows(string $path, string $extension): array
    {
        $extension = strtolower($extension);
        if ($extension === 'csv') {
            return self::csvRows($path);
        }
        if ($extension === 'xlsx') {
            return self::xlsxRows($path);
        }
        throw new RuntimeException('Use the Excel format (.xlsx). You can also upload a CSV saved from that file.');
    }

    /**
     * @return list<array{row: int, cells: list<string>}>
     */
    private static function csvRows(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Could not read the file.');
        }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        } elseif (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
        $rows = [];
        $number = 0;
        foreach ($lines as $line) {
            $number++;
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line);
            $cells = array_map(static fn ($cell): string => trim((string) $cell), $cells);
            if (implode('', $cells) === '') {
                continue;
            }
            $rows[] = ['row' => $number, 'cells' => $cells];
        }
        return $rows;
    }

    /**
     * @return list<array{row: int, cells: list<string>}>
     */
    private static function xlsxRows(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Excel support is not enabled on this server.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open that Excel file. Save it as .xlsx and try again.');
        }
        $shared = self::sharedStrings($zip);
        $sheetName = self::firstSheetPath($zip);
        $xml = $sheetName ? $zip->getFromName($sheetName) : false;
        $zip->close();
        if ($xml === false || $xml === '') {
            throw new RuntimeException('The Excel file has no Accounts sheet.');
        }

        $dom = self::xml($xml);
        $xpath = new DOMXPath($dom);
        $rowNodes = $xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]');
        $rows = [];
        $fallback = 0;
        foreach ($rowNodes ?: [] as $rowNode) {
            $fallback++;
            if ($fallback > 2000) {
                throw new RuntimeException('The Excel file has too many rows. Import up to 500 accounts.');
            }
            $rowNumber = (int) $rowNode->getAttribute('r');
            if ($rowNumber < 1) {
                $rowNumber = $fallback;
            }
            $cells = [];
            $cellNodes = $xpath->query('./*[local-name()="c"]', $rowNode);
            foreach ($cellNodes ?: [] as $cell) {
                $index = self::columnIndex($cell->getAttribute('r'));
                $cells[$index] = self::cellText($xpath, $cell, $shared);
            }
            if ($cells === []) {
                continue;
            }
            ksort($cells);
            $max = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $max; $i++) {
                $line[] = trim($cells[$i] ?? '');
            }
            if (implode('', $line) === '') {
                continue;
            }
            $rows[] = ['row' => $rowNumber, 'cells' => $line];
        }
        return $rows;
    }

    /** @return list<string> */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false || $xml === '') {
            return [];
        }
        $xpath = new DOMXPath(self::xml($xml));
        $nodes = $xpath->query('//*[local-name()="si"]');
        $shared = [];
        foreach ($nodes ?: [] as $node) {
            $text = '';
            $parts = $xpath->query('.//*[local-name()="t"]', $node);
            foreach ($parts ?: [] as $part) {
                $text .= $part->textContent;
            }
            $shared[] = $text;
        }
        return $shared;
    }

    private static function firstSheetPath(ZipArchive $zip): ?string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }
        $book = new DOMXPath(self::xml($workbook));
        $sheet = $book->query('//*[local-name()="sheet"]')->item(0);
        if (!$sheet instanceof \DOMElement) {
            return 'xl/worksheets/sheet1.xml';
        }
        $rid = $sheet->getAttribute('r:id');
        if ($rid === '') {
            $rid = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
        }
        $relDoc = new DOMXPath(self::xml($rels));
        $relationships = $relDoc->query('//*[local-name()="Relationship"]');
        foreach ($relationships ?: [] as $rel) {
            if (!$rel instanceof \DOMElement || $rel->getAttribute('Id') !== $rid) {
                continue;
            }
            $target = ltrim($rel->getAttribute('Target'), '/');
            if (!str_starts_with($target, 'xl/')) {
                $target = 'xl/' . $target;
            }
            return $target;
        }
        return 'xl/worksheets/sheet1.xml';
    }

    /** @param list<string> $shared */
    private static function cellText(DOMXPath $xpath, \DOMElement $cell, array $shared): string
    {
        $type = $cell->getAttribute('t');
        if ($type === 'inlineStr') {
            $text = '';
            $parts = $xpath->query('.//*[local-name()="t"]', $cell);
            foreach ($parts ?: [] as $part) {
                $text .= $part->textContent;
            }
            return $text;
        }
        $value = $xpath->query('./*[local-name()="v"]', $cell)->item(0);
        $raw = $value ? $value->textContent : '';
        if ($type === 's') {
            return $shared[(int) $raw] ?? '';
        }
        if ($type === 'b') {
            return $raw === '1' ? 'yes' : 'no';
        }
        return $raw;
    }

    private static function columnIndex(string $ref): int
    {
        $letters = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $ref));
        if ($letters === '') {
            return 0;
        }
        $index = 0;
        $length = strlen($letters);
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return $index - 1;
    }

    private static function xml(string $xml): DOMDocument
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $dom;
    }

    private static function contentTypes(int $sheetCount): string
    {
        $overrides = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides
            . '</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    /** @param list<array{name: string, rows: list<list<string>>}> $sheets */
    private static function workbook(array $sheets): string
    {
        $items = '';
        foreach ($sheets as $index => $sheet) {
            $name = htmlspecialchars($sheet['name'], ENT_XML1);
            $n = $index + 1;
            $items .= '<sheet name="' . $name . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $items . '</sheets></workbook>';
    }

    private static function workbookRels(int $sheetCount): string
    {
        $items = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $items .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $styleId = $sheetCount + 1;
        $items .= '<Relationship Id="rId' . $styleId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $items
            . '</Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="1"><xf/></cellXfs>'
            . '</styleSheet>';
    }

    /** @param list<list<string>> $rows */
    private static function worksheet(array $rows): string
    {
        $body = '';
        foreach ($rows as $r => $row) {
            $rowNumber = $r + 1;
            $cells = '';
            foreach ($row as $c => $value) {
                $ref = self::columnName($c) . $rowNumber;
                $text = self::xmlText($value);
                $cells .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
            }
            $body .= '<row r="' . $rowNumber . '">' . $cells . '</row>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . $body
            . '</sheetData></worksheet>';
    }

    private static function columnName(int $index): string
    {
        $name = '';
        $n = $index + 1;
        while ($n > 0) {
            $n--;
            $name = chr(65 + ($n % 26)) . $name;
            $n = intdiv($n, 26);
        }
        return $name;
    }

    private static function xmlText(string $value): string
    {
        $value = preg_replace('/[^\P{C}\t\n\r]/u', '', $value) ?? '';
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
