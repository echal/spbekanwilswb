<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

abstract class BaseExport implements FromQuery, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize, WithEvents
{
    protected array $filters = [];

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Judul sheet — override di subclass jika perlu
     */
    public function title(): string
    {
        return 'Data';
    }

    /**
     * Judul baris pertama (merge cell) — override di subclass
     */
    abstract protected function sheetTitle(): string;

    /**
     * Jumlah kolom — dibutuhkan untuk merge cell judul
     */
    abstract protected function columnCount(): int;

    public function styles(Worksheet $sheet): array
    {
        $lastCol = $this->columnIndexToLetter($this->columnCount());
        $headingRow = 2; // baris 1 = judul, baris 2 = heading

        return [
            // Judul besar
            1 => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            ],
            // Header kolom
            $headingRow => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2E75B6']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $this->columnIndexToLetter($this->columnCount());

                // Merge cell untuk judul di baris 1
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getRowDimension(2)->setRowHeight(20);

                // Border untuk semua data
                $lastRow = $sheet->getHighestRow();
                if ($lastRow >= 3) {
                    $sheet->getStyle("A2:{$lastCol}{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FFB0B0B0'],
                            ],
                        ],
                    ]);
                }

                // Zebra stripe untuk baris data
                for ($row = 3; $row <= $lastRow; $row++) {
                    if ($row % 2 === 0) {
                        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF0F4FA']],
                        ]);
                    }
                }

                // Freeze header
                $sheet->freezePane('A3');
            },
        ];
    }

    /**
     * Prepend baris judul sebelum heading — dipanggil via WithHeadings
     * Subclass harus return array heading saja; judul ditangani event AfterSheet.
     */
    public function headings(): array
    {
        return array_merge(
            [array_fill(0, $this->columnCount(), $this->sheetTitle())],
            [$this->columnHeadings()]
        );
    }

    /**
     * Array heading kolom — override di subclass
     */
    abstract protected function columnHeadings(): array;

    protected function columnIndexToLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index = (int)(($index - $mod) / 26);
        }
        return $letter;
    }

    protected function formatDate($date): string
    {
        if (!$date) return '-';
        return \Carbon\Carbon::parse($date)->format('d/m/Y');
    }

    protected function formatDateTime($date): string
    {
        if (!$date) return '-';
        return \Carbon\Carbon::parse($date)->format('d/m/Y H:i');
    }
}
