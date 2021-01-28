<?php
namespace ubfr\c5tools;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

abstract class TabularReport extends Report
{
    use TabularChecks, TabularParsers;

    protected $spreadsheet;

    protected $tabularHeaders;

    protected $filters;

    protected $columnMap;

    protected $reportingPeriodTotalColumn;

    public function __construct($spreadsheet)
    {
        if (! ($spreadsheet instanceof Spreadsheet)) {
            throw new \InvalidArgumentException('System Error - TabularReport: argument must be Spreadsheet');
        }
        $this->spreadsheet = $spreadsheet;
        $this->tabularHeaders = [];
        $this->filters = [];
        $this->columnMap = [];
        $this->reportingPeriodTotalColumn = null;

        parent::__construct();
    }

    protected function getFormat()
    {
        return self::FORMAT_TABULAR;
    }

    protected function getGranularity()
    {
        static $granularity = null;

        if ($granularity === null) {
            $attributes = $this->getReportAttributes();
            if (isset($attributes['Exclude_Monthly_Details'])) {
                $granularity = self::GRANULARITY_TOTALS;
            } else {
                $granularity = self::GRANULARITY_MONTH;
            }
        }

        return $granularity;
    }

    protected function getColumnForElement($element)
    {
        static $elementColumn = [];

        if (! isset($elementColumn[$element])) {
            foreach ($this->columnMap as $columnNumber => $columnMap) {
                if ($columnMap['columnHeading'] === $element) {
                    $elementColumn[$element] = $columnNumber;
                    break;
                }
            }
            if (! isset($elementColumn[$element])) {
                $elementColumn[$element] = false;
            }
        }

        return $elementColumn[$element];
    }

    protected function originalReportHeadersAsWorksheet($worksheet)
    {
        foreach ($this->tabularHeaders as $cell => $value) {
            $worksheet->setCellValue($cell, $value);
        }
        $worksheet->getColumnDimension('A')->setAutoSize(true);
        $worksheet->getColumnDimension('B')->setAutoSize(true);
    }
}