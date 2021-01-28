<?php
namespace ubfr\c5tools;

abstract class JsonReport extends Report
{
    use JsonChecks, JsonParsers;

    protected $json = null;

    protected $jsonHeaders;

    protected $currentDate;

    protected $currentDates;

    public function __construct($json)
    {
        if (! is_object($json)) {
            throw new \InvalidArgumentException('System Error - JsonReport: argument must be decoded JSON report');
        }
        $this->json = $json;
        $this->jsonHeaders = null;

        parent::__construct();
    }

    protected function getFormat()
    {
        return self::FORMAT_JSON;
    }

    protected function getGranularity()
    {
        static $granularity = null;

        if ($granularity === null) {
            $attributes = $this->getReportAttributes();
            if (isset($attributes['Granularity'])) {
                $granularity = self::GRANULARITY_TOTALS;
            } else {
                $granularity = self::GRANULARITY_MONTH;
            }
        }

        return $granularity;
    }

    protected function originalReportHeadersAsWorksheet($worksheet)
    {
        $row = 1;
        foreach (explode("\n", json_encode($this->jsonHeaders, JSON_PRETTY_PRINT)) as $line) {
            $worksheet->setCellValue("A{$row}", $line);
            $row ++;
        }
        $worksheet->getColumnDimension('A')->setAutoSize(true);
    }
}
