<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exportacion generica a Excel: recibe un titulo de hoja, los encabezados y las
 * filas ya aplanadas (array de arrays). Evita crear una clase por reporte: cada
 * reporte arma sus filas en el controlador y las pasa aqui.
 */
class ReporteExport implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        private string $titulo,
        private array $encabezados,
        private array $filas,
    ) {}

    public function array(): array
    {
        return $this->filas;
    }

    public function headings(): array
    {
        return $this->encabezados;
    }

    public function title(): string
    {
        // El titulo de hoja de Excel no admite mas de 31 caracteres.
        return mb_substr($this->titulo, 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        // Fila de encabezados en negrita.
        return [1 => ['font' => ['bold' => true]]];
    }
}
