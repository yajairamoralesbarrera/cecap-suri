<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class SimpleXlsxWriter
{
    /**
     * Generates a standards-compliant multi-sheet OOXML (.xlsx) file in a temporary path
     * and returns its binary contents.
     *
     * @param  array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, string|int|float|null>>}>  $sheets
     */
    public static function build(array $sheets): string
    {
        if (empty($sheets)) {
            throw new RuntimeException('El libro Excel debe contener al menos una hoja.');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'cecap_xlsx_');
        if ($tmpFile === false) {
            throw new RuntimeException('No se pudo crear archivo temporal para Excel.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpFile);
            throw new RuntimeException('No se pudo inicializar el paquete XLSX.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypesXml(count($sheets)));
        $zip->addFromString('_rels/.rels', self::rootRelsXml());
        $zip->addFromString('xl/workbook.xml', self::workbookXml($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml(count($sheets)));
        $zip->addFromString('xl/styles.xml', self::stylesXml());

        foreach ($sheets as $idx => $sheet) {
            $sheetNum = $idx + 1;
            $zip->addFromString(
                "xl/worksheets/sheet{$sheetNum}.xml",
                self::worksheetXml($sheet['headers'], $sheet['rows'])
            );
        }

        $zip->close();

        $binary = (string) file_get_contents($tmpFile);
        @unlink($tmpFile);

        return $binary;
    }

    private static function xmlEscape(string $value): string
    {
        // Strip invalid XML 1.0 control characters
        $clean = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';

        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function columnLetter(int $index): string
    {
        $letter = '';
        $num = $index + 1;
        while ($num > 0) {
            $mod = ($num - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $num = intdiv($num - $mod, 26);
        }

        return $letter;
    }

    private static function contentTypesXml(int $sheetCount): string
    {
        $overrides = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides
            .'</Types>';
    }

    private static function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function workbookXml(array $sheets): string
    {
        $sheetNodes = '';
        foreach ($sheets as $idx => $sheet) {
            $num = $idx + 1;
            // Excel sheet names max 31 chars, no []:*?/\
            $safeTitle = preg_replace('/[\[\]:\*\?\/\\\\]/', ' ', (string) ($sheet['title'] ?? "Hoja {$num}")) ?? "Hoja {$num}";
            $safeTitle = mb_substr(trim($safeTitle), 0, 31) ?: "Hoja {$num}";
            $sheetNodes .= '<sheet name="'.self::xmlEscape($safeTitle).'" sheetId="'.$num.'" r:id="rId'.$num.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheetNodes.'</sheets>'
            .'</workbook>';
    }

    private static function workbookRelsXml(int $sheetCount): string
    {
        $rels = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }
        $stylesId = $sheetCount + 1;
        $rels .= '<Relationship Id="rId'.$stylesId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'</Relationships>';
    }

    private static function stylesXml(): string
    {
        // Style 0: default text; Style 1: bold white header on CeCap dark green (#1E3A2F)
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF1E3A2F"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border>'
            .'<left style="thin"><color rgb="FFD1D5DB"/></left>'
            .'<right style="thin"><color rgb="FFD1D5DB"/></right>'
            .'<top style="thin"><color rgb="FFD1D5DB"/></top>'
            .'<bottom style="thin"><color rgb="FFD1D5DB"/></bottom>'
            .'<diagonal/>'
            .'</border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    private static function worksheetXml(array $headers, array $rows): string
    {
        $colWidths = [];
        foreach ($headers as $cIdx => $h) {
            $colWidths[$cIdx] = max(12, min(42, mb_strlen((string) $h) + 4));
        }

        foreach ($rows as $row) {
            foreach ($row as $cIdx => $val) {
                $len = mb_strlen((string) ($val ?? ''));
                $colWidths[$cIdx] = max($colWidths[$cIdx] ?? 12, min(42, $len + 3));
            }
        }

        $colsXml = '<cols>';
        foreach ($colWidths as $cIdx => $width) {
            $colNum = $cIdx + 1;
            $colsXml .= '<col min="'.$colNum.'" max="'.$colNum.'" width="'.$width.'" customWidth="1"/>';
        }
        $colsXml .= '</cols>';

        $sheetData = '<sheetData>';

        // Header row (row 1)
        $sheetData .= '<row r="1" ht="22" customHeight="1">';
        foreach ($headers as $cIdx => $header) {
            $ref = self::columnLetter($cIdx).'1';
            $sheetData .= '<c r="'.$ref.'" t="inlineStr" s="1"><is><t>'.self::xmlEscape((string) $header).'</t></is></c>';
        }
        $sheetData .= '</row>';

        // Data rows
        $rowNum = 2;
        foreach ($rows as $row) {
            $sheetData .= '<row r="'.$rowNum.'">';
            foreach (array_values($row) as $cIdx => $value) {
                $ref = self::columnLetter($cIdx).$rowNum;
                if (is_int($value) || is_float($value)) {
                    $sheetData .= '<c r="'.$ref.'" s="0"><v>'.$value.'</v></c>';
                } else {
                    $sheetData .= '<c r="'.$ref.'" t="inlineStr" s="0"><is><t>'.self::xmlEscape((string) ($value ?? '')).'</t></is></c>';
                }
            }
            $sheetData .= '</row>';
            $rowNum++;
        }

        $sheetData .= '</sheetData>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$colsXml
            .$sheetData
            .'</worksheet>';
    }
}
