<?php

namespace App\Filament\Exports;

use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Exporter;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;

abstract class BaseExporter extends Exporter
{
    /**
     * Sediakan dua opsi (CSV dan XLSX); XLSX tetap akan memakai OpenSpout + styling.
     *
     * @return array<int, ExportFormat>
     */
    public function getFormats(): array
    {
        return [ExportFormat::Csv, ExportFormat::Xlsx];
    }

    public function getXlsxCellStyle(): ?Style
    {
        return (new Style)
            ->setFontName('Segoe UI')
            ->setFontSize(11)
            ->setCellAlignment(CellAlignment::LEFT)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText(true);
    }

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style)
            ->setFontBold()
            ->setFontSize(12)
            ->setBackgroundColor(Color::rgb(0, 0, 139)) // dark blue
            ->setFontColor(Color::WHITE)
            ->setCellAlignment(CellAlignment::LEFT)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText(true);
    }

    public function getXlsxWriterOptions(): ?Options
    {
        $options = new Options;
        $options->DEFAULT_ROW_STYLE = $this->getXlsxCellStyle() ?? $options->DEFAULT_ROW_STYLE;
        $this->setColumnWidths($options);
        $options->setPageSetup(new PageSetup(
            PageOrientation::LANDSCAPE,
            PaperSize::A4,
            null,
            1 // fit sheet to one page wide
        ));

        return $options;
    }

    /**
     * Buat lebar kolom agar lebih pas dibaca tanpa harus autosize (OpenSpout tidak autosize).
     */
    protected function setColumnWidths(Options $options): void
    {
        $labels = array_values($this->columnMap);
        $columnCount = count($labels);

        if ($columnCount === 0) {
            return;
        }

        foreach ($labels as $index => $label) {
            $length = strlen((string) $label);
            $width = max(14, min(42, (int) ceil($length * 1.35)));

            // Kolom 1-based di setColumnWidth.
            $options->setColumnWidth($width, $index + 1);
        }
    }
}
