<?php

namespace App\Modules\Reporting\Services;

use App\Support\SalesReportRowFormatter;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

class WorkbookService
{
    private const DATA_ROWS_PER_SHEET = 1_048_575;

    /**
     * @param  callable(int): void|null  $progressCallback
     * @return array{rows_written:int, sheet_count:int}
     */
    public function convertCsvToXlsx(string $csvPath, string $xlsxPath, ?callable $progressCallback = null): array
    {
        if (! is_file($csvPath)) {
            throw new RuntimeException('The CSV source file could not be found for workbook generation.');
        }

        File::ensureDirectoryExists(dirname($xlsxPath));

        $tempDirectory = storage_path('app/openspout-temp');
        File::ensureDirectoryExists($tempDirectory);

        $options = new Options;
        $options->setTempFolder($tempDirectory);
        $options->SHOULD_CREATE_NEW_SHEETS_AUTOMATICALLY = false;

        $writer = new Writer($options);
        $writer->setCreator('pharamaPOC');
        $writer->openToFile($xlsxPath);
        $writerOpen = true;

        $headingStyle = (new Style)
            ->setFontBold()
            ->setBackgroundColor('E8F1FF');

        $handle = fopen($csvPath, 'rb');

        if (! $handle) {
            $writer->close();
            $writerOpen = false;

            throw new RuntimeException('The CSV source file could not be opened for workbook generation.');
        }

        try {
            $headings = fgetcsv($handle) ?: SalesReportRowFormatter::headings();
            $sheetIndex = 1;
            $dataRowsWritten = 0;
            $sheetRowsWritten = 0;

            $writer->getCurrentSheet()->setName($this->sheetName($sheetIndex));
            $writer->addRow(Row::fromValues($headings, $headingStyle));

            while (($row = fgetcsv($handle)) !== false) {
                if ($sheetRowsWritten >= self::DATA_ROWS_PER_SHEET) {
                    $sheetIndex++;
                    $sheetRowsWritten = 0;

                    $writer->addNewSheetAndMakeItCurrent()->setName($this->sheetName($sheetIndex));
                    $writer->addRow(Row::fromValues($headings, $headingStyle));
                }

                $writer->addRow(Row::fromValues($row));

                $dataRowsWritten++;
                $sheetRowsWritten++;

                if ($progressCallback) {
                    $progressCallback($dataRowsWritten);
                }
            }

            $writer->close();
            $writerOpen = false;

            return [
                'rows_written' => $dataRowsWritten,
                'sheet_count' => $sheetIndex,
            ];
        } finally {
            if ($writerOpen) {
                $writer->close();
            }
            fclose($handle);
        }
    }

    private function sheetName(int $sheetIndex): string
    {
        return "Sales Report {$sheetIndex}";
    }
}
