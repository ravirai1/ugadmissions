<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Small .xlsx builder. Text is always written as text, so a value such as "=HYPERLINK(...)" typed by an
 * applicant can never run as a formula in Excel.
 */
final class Excel
{
    /** @param  array<string, array{headers: string[], rows: iterable<array>}>  $sheets  sheet title => data */
    public static function download(string $filename, array $sheets): StreamedResponse
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator("St. Stephen's College UG Admissions")->setTitle($filename);
        $book->removeSheetByIndex(0);

        foreach ($sheets as $title => $data) {
            $sheet = $book->createSheet();
            $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $title), 0, 31));
            $sheet->fromArray([$data['headers']]);
            $sheet->getStyle('1:1')->getFont()->setBold(true);
            $sheet->freezePane('A2');

            $r = 2;
            foreach ($data['rows'] as $row) {
                $c = 1;
                foreach ($row as $value) {
                    $cell = $sheet->getCell([$c, $r]);
                    if (is_int($value) || is_float($value)) {
                        $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
                    } else {
                        $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
                    }
                    $c++;
                }
                $r++;
            }
            foreach (range(1, max(1, count($data['headers']))) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            }
        }
        $book->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
