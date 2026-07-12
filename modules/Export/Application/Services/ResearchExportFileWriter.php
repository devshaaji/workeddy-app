<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Application\Services;

final class ResearchExportFileWriter
{
    /**
     * @param list<array<string, scalar|null>> $rows
     */
    public function write(string $format, string $dataset, array $rows): string
    {
        return match (strtolower($format)) {
            'xlsx' => $this->writeXlsx($dataset, $rows),
            default => $this->writeCsv($dataset, $rows),
        };
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     */
    private function writeCsv(string $dataset, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'research_export_');
        if ($path === false) {
            throw new \RuntimeException('Failed to create temp export file.');
        }
        $target = $path . '_' . $dataset . '.csv';
        rename($path, $target);

        $fp = fopen($target, 'wb');
        if ($fp === false) {
            throw new \RuntimeException('Failed to open export file for writing.');
        }

        $headers = array_keys($rows[0] ?? []);
        if ($headers !== []) {
            fputcsv($fp, $headers);
        }
        foreach ($rows as $row) {
            fputcsv($fp, array_map(static fn($value): string => $value === null ? '' : (string) $value, $row));
        }
        fclose($fp);

        return $target;
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     */
    private function writeXlsx(string $dataset, array $rows): string
    {
        $zip = new \ZipArchive();
        $path = tempnam(sys_get_temp_dir(), 'research_export_');
        if ($path === false) {
            throw new \RuntimeException('Failed to create temp export file.');
        }
        $target = $path . '_' . $dataset . '.xlsx';
        rename($path, $target);

        if ($zip->open($target, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create XLSX export file.');
        }

        $headers = array_keys($rows[0] ?? []);
        $sheetRows = [];
        if ($headers !== []) {
            $sheetRows[] = $headers;
        }
        foreach ($rows as $row) {
            $sheetRows[] = array_map(static fn($value): string => $value === null ? '' : (string) $value, $row);
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addEmptyDir('_rels');
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addEmptyDir('xl');
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($dataset));
        $zip->addEmptyDir('xl/_rels');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addEmptyDir('xl/worksheets');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($sheetRows));
        $zip->close();

        return $target;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(string $dataset): string
    {
        $name = htmlspecialchars(substr($dataset, 0, 31), ENT_XML1);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
    }

    /**
     * @param list<list<string>> $rows
     */
    private function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $xml .= '<row r="' . ($rowIndex + 1) . '">';
            foreach ($row as $colIndex => $value) {
                $cell = $this->columnName($colIndex + 1) . ($rowIndex + 1);
                $xml .= '<c r="' . $cell . '" t="inlineStr"><is><t>'
                    . htmlspecialchars($value, ENT_XML1)
                    . '</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml . '</sheetData></worksheet>';
    }

    private function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)) . $name;
            $column = intdiv($column, 26);
        }

        return $name;
    }
}
