<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;


class ExportView implements FromView, WithTitle, WithColumnFormatting, WithStrictNullComparison
{
    private $view_name;
    private $data;
    private $title;
    private $format_columns;

    public function __construct($view_name, $data, $title, $format_columns = [])
    {
        $this->view_name = $view_name;
        $this->data = $data;
        $this->title = $title;
        $this->format_columns = $format_columns;
    }

    public function view(): View
    {
        // dd($this->createData());
        return view($this->view_name, ['data' => $this->createData()]);
    }

    public function createData()
    {
        return $this->data;
    }

    function title(): string
    {
        return $this->title;
    }

    function columnFormats(): array
    {
        $arr = [];
        foreach ($this->format_columns as $column) {
            $arr[$column] = NumberFormat::FORMAT_NUMBER;
        }
        return $arr;
    }
}
