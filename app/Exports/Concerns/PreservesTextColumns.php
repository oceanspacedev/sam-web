<?php

namespace App\Exports\Concerns;

use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

trait PreservesTextColumns
{
    abstract protected function textColumns(): array;

    public function bindValue(Cell $cell, $value): bool
    {
        if (in_array($cell->getColumn(), $this->textColumns(), true)) {
            $cell->setValueExplicit((string) ($value ?? ''), DataType::TYPE_STRING);

            return true;
        }

        return (new DefaultValueBinder)->bindValue($cell, $value);
    }

    public function columnFormats(): array
    {
        return array_fill_keys($this->textColumns(), NumberFormat::FORMAT_TEXT);
    }
}
