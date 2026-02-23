<?php

namespace App\Filament\Exports;

use Carbon\Carbon;
use DateTimeInterface;
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
use Throwable;

abstract class BaseExporter extends Exporter
{
    /**
     * Hanya sediakan XLSX (dengan styling OpenSpout).
     *
     * @return array<int, ExportFormat>
     */
    public function getFormats(): array
    {
        return [ExportFormat::Xlsx];
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

    protected static function formatDateTimeValue(null|string|DateTimeInterface $state, string $format, string $fallback = '-'): string
    {
        if ($state === null || $state === '') {
            return $fallback;
        }

        if ($state instanceof DateTimeInterface) {
            return $state->format($format);
        }

        try {
            return Carbon::parse($state)->format($format);
        } catch (Throwable) {
            return $fallback;
        }
    }

    protected static function storageUrl(?string $path, string $fallback = '-'): string
    {
        if ($path === null || $path === '') {
            return $fallback;
        }

        $baseUrl = rtrim(config('app.url'), '/').'/storage/';

        return $baseUrl.ltrim($path, '/');
    }

    protected static function storageImageFormula(?string $path, string $fallback = '-'): string
    {
        // Try IMAGE() (Excel 365) and fall back to clickable hyperlink if unsupported.
        $url = static::storageUrl($path, '');

        if ($url === '') {
            return $fallback;
        }

        // IFERROR handles older Excel versions that don't support IMAGE().
        return sprintf('=IFERROR(IMAGE("%1$s"),HYPERLINK("%1$s","%1$s"))', $url);
    }

    protected static function mapLinkFromLatLong(?string $latlong, string $fallback = '-'): string
    {
        $value = trim((string) $latlong);

        if ($value === '') {
            return $fallback;
        }

        $encoded = rawurlencode($value);
        $label = str_replace('"', '""', $value);
        $url = "https://www.google.com/maps/search/?api=1&query={$encoded}";

        return sprintf('=HYPERLINK("%s","%s")', $url, $label);
    }
}
