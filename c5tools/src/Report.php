<?php
namespace ubfr\c5tools;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class Report
{
    use Checks, Parsers, Helpers;

    const FORMAT_JSON = 'json';

    const FORMAT_TABULAR = 'tabular';

    const GRANULARITY_MONTH = 'Month';

    const GRANULARITY_TOTALS = 'Totals';

    protected $filename;

    protected $mimetype;

    protected $config;

    protected $checkResult;

    protected $release;

    protected $beginDate;

    protected $endDate;

    protected $headers;

    protected $elements;

    protected $values;

    protected $currentItem;

    protected $currentParent;

    protected $currentComponent;

    protected $currentComponents;

    protected $currentSectionTypePosition;

    protected $metricTypePresent;

    protected $platforms;

    protected $items;

    protected $body;

    protected abstract function getFormat();

    protected abstract function getGranularity();

    protected abstract function parse();

    protected abstract function originalReportHeadersAsWorksheet($worksheet);

    public function __construct()
    {
        $this->filename = null;
        $this->mimetype = null;
        $this->checkResult = new CheckResult();
        $this->beginDate = null;
        $this->endDate = null;
        $this->headers = [];
        $this->elements = [];
        $this->values = [];
        $this->currentItem = [];
        $this->currentParent = [];
        $this->currentComponent = [];
        $this->currentComponents = [];
        $this->metricTypePresent = [];
        $this->platforms = [];
        $this->items = [];
        $this->body = [];

        try {
            $this->parse();
        } catch (ParseException $e) {
            // corresponding fatal error is part of the CheckResult
        }
    }

    public static function createFromFile($filename, $extension = null)
    {
        if (! file_exists($filename)) {
            throw new \Exception("System Error - file {$filename} not found");
        }
        if (! is_file($filename)) {
            throw new \Exception("System Error - {$filename} is not a file");
        }
        if (! is_readable($filename)) {
            throw new \Exception("System Error - file {$filename} is not readable");
        }

        // TODO: better type detection based on content of the file
        if ($extension === null) {
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        }
        switch ($extension) {
            case 'ods':
            case 'xls':
            case 'xlsx':
                return self::tabularReportFromFile($filename, $extension);
                break;
            case 'csv':
            // case 'txt':
            case 'tsv':
                return self::tabularReportFromFile($filename, $extension, true);
                break;
            case 'json':
                return self::jsonReportFromFile($filename);
                break;
            default:
                throw new \Exception("System Error - file extension {$extension} not supported");
                break;
        }
    }

    protected static function tabularReportFromFile($filename, $extension, $checkEncoding = false)
    {
        static $extension2mimetype = [
            'csv' => 'text/csv',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'tsv' => 'text/tab-separated-values',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        $fileHasBOM = false;
        if ($checkEncoding) {
            $finfo = new \finfo();
            $encoding = $finfo->file($filename, FILEINFO_MIME_ENCODING);
            if ($encoding !== 'us-ascii' && $encoding !== 'utf-8') {
                throw new ParseException("File encoding {$encoding} is invalid, encoding must be UTF-8");
            }

            $bom = pack('H*', 'EFBBBF');
            if (file_get_contents($filename, false, null, 0, 3) === $bom) {
                $fileHasBOM = true;
            }
        }

        $reader = IOFactory::createReaderForFile($filename);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $spreadsheet = $reader->load($filename);
        try {
            $release = self::getReleaseFromSpreadsheet($spreadsheet);
        } catch (\Exception $e) {
            if ($reader instanceof Csv && ($extension === 'csv' || $extension === 'tsv')) {
                // ignore exception, automatic delimiter detection might have failed
                $release = false;
            } else {
                throw new ParseException("Could not determine COUNTER Release - " . $e->getMessage());
            }
        }
        if ($release === false) {
            // try again with explicitly set delimiter
            $reader->setDelimiter($extension === 'csv' ? ',' : "\t");
            $spreadsheet = $reader->load($filename);
            try {
                $release = self::getReleaseFromSpreadsheet($spreadsheet);
            } catch (\Exception $e) {
                throw new ParseException("Could not determine COUNTER Release - " . $e->getMessage());
            }
        }
        if ($release !== '5') {
            throw new ParseException("COUNTER Release '{$release}' is invalid or unsupported");
        }

        $report = new TabularR5Report($spreadsheet);
        $report->filename = $filename;
        $report->mimetype = $extension2mimetype[$extension];

        if ($fileHasBOM) {
            $message = 'File contains byte order mark (BOM)';
            $report->checkResult->addWarning($message, $message, 1, '0xEF 0xBB 0xBF',
                'byte order has no meaning in UTF-8, using a BOM is not recommended');
        }

        return $report;
    }

    protected static function getReleaseFromSpreadsheet($spreadsheet)
    {
        $releaseLabelCell = $spreadsheet->getActiveSheet()->getCell('A3', false);
        $releaseValueCell = $spreadsheet->getActiveSheet()->getCell('B3', false);
        if (! $releaseLabelCell || ! $releaseValueCell) {
            throw new \Exception("cell A3 or B3 is empty");
        }
        if (strtolower(trim($releaseLabelCell->getValue())) !== 'release') {
            throw new \Exception("expecting 'Release' in cell A3, found '" . $releaseLabelCell->getValue() . "'");
        }

        return trim($releaseValueCell->getValue());
    }

    protected static function jsonReportFromFile($filename)
    {
        $finfo = new \finfo();
        $encoding = $finfo->file($filename, FILEINFO_MIME_ENCODING);
        if ($encoding !== 'us-ascii' && $encoding !== 'utf-8') {
            throw new ParseException("File encoding {$encoding} is invalid, encoding must be UTF-8");
        }

        $buffer = file_get_contents($filename);
        if ($buffer === false) {
            throw new \Exception("System Error - reading file {$filename} failed");
        }

        return self::jsonReportFromBuffer($buffer, $filename);
    }

    // TODO: encapsulate in generic createFromBuffer method
    public static function jsonReportFromBuffer($buffer, $filename = null)
    {
        $bom = pack('H*', 'EFBBBF');
        $bufferHasBOM = false;
        if (substr($buffer, 0, strlen($bom)) === $bom) {
            $buffer = substr($buffer, strlen($bom));
            $bufferHasBOM = true;
        }

        $json = json_decode($buffer);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ParseException("Error decoding JSON - " . json_last_error_msg());
        }
        unset($buffer);

        try {
            $release = self::getReleaseFromJson($json);
        } catch (\Exception $e) {
            throw new ParseException("Could not determine COUNTER Release - " . $e->getMessage());
        }
        if ($release !== '5') {
            throw new ParseException("COUNTER Release '{$release}' invalid/unsupported");
        }

        $report = new JsonR5Report($json);
        unset($json);
        $report->filename = $filename;
        $report->mimetype = 'application/json';

        if ($bufferHasBOM) {
            $message = ($filename !== null ? 'File' : 'JSON string') . ' contains byte order mark (BOM)';
            $report->checkResult->addWarning($message, $message, 1, '0xEF 0xBB 0xBF',
                'byte order has no meaning in UTF-8, using a BOM is not recommended');
        }

        return $report;
    }

    protected static function getReleaseFromJson(&$json)
    {
        if (! is_object($json)) {
            throw new \Exception('JSON must be an object, found ' . (is_array($json) ? 'an array' : 'a scalar'));
        }
        if (property_exists($json, 'Code') && property_exists($json, 'Message')) {
            throw new \Exception('JSON must be a Report, found an Exception');
        }
        if (! property_exists($json, 'Report_Header')) {
            throw new \Exception('Report_Header is missing');
        }
        $header = $json->Report_Header;
        if (! is_object($header)) {
            throw new \Exception(
                'Report_Header must be an object, found ' . (is_array($header) ? 'an array' : 'a scalar'));
        }
        if (! property_exists($header, 'Release')) {
            throw new \Exception('Report_Header.Release is missing');
        }
        if (! is_scalar($header->Release)) {
            throw new \Exception(
                'Report_Header.Release must be a scalar, found an ' . (is_array($header->Release) ? 'array' : 'object'));
        }

        return trim($header->Release);
    }

    public function getCheckResult()
    {
        return $this->checkResult;
    }

    public function getReportId()
    {
        if (! isset($this->headers['Report_ID'])) {
            throw new \Exception('System Error - Report: Report_ID is missing');
        }

        return $this->headers['Report_ID'];
    }

    public function getReportName()
    {
        return ($this->headers['Report_Name'] ?? $this->config->getReportName($this->getReportId()));
    }

    public function getRelease()
    {
        if (! isset($this->headers['Release'])) {
            throw new \Exception('System Error - Report: Release is missing');
        }

        return $this->headers['Release'];
    }

    public function getInstitutionName()
    {
        return ($this->headers['Institution_Name'] ?? null);
    }

    public function getCreated()
    {
        return ($this->headers['Created'] ?? null);
    }

    public function getCreatedBy()
    {
        return ($this->headers['Created_By'] ?? null);
    }

    public function getBeginDate()
    {
        return $this->beginDate;
    }

    public function getEndDate()
    {
        return $this->endDate;
    }

    public function getReportAttributes()
    {
        if (! isset($this->headers['Report_Attributes'])) {
            return [];
        }

        $attributes = [];
        foreach ($this->headers['Report_Attributes'] as $name => $valuePosition) {
            $attributes[$name] = $valuePosition['v'];
        }

        return $attributes;
    }

    public function getReportFilters()
    {
        if (! isset($this->headers['Report_Filters'])) {
            return [];
        }

        $filters = [];
        foreach ($this->headers['Report_Filters'] as $name => $valuePosition) {
            $filters[$name] = $valuePosition['v'];
        }

        return $filters;
    }

    public function isMasterReport()
    {
        if (! isset($this->headers['Report_ID'])) {
            throw new \Exception('System Error - Report: Report_ID missing');
        }

        return $this->config->isMasterReport($this->headers['Report_ID']);
    }

    protected function includesParentDetails()
    {
        static $includesParentDetails = null;

        if ($this->getReportId() !== 'IR') {
            throw new \Exception("System Error - Report: includesParentDetails can only be used for IR");
        }

        if ($includesParentDetails === null) {
            $attributes = $this->getReportAttributes();
            $includesParentDetails = isset($attributes['Include_Parent_Details']);
        }

        return $includesParentDetails;
    }

    protected function includesParentData()
    {
        if ($this->getReportId() === 'IR') {
            return $this->includesParentDetails();
        }
        return ($this->getReportId() === 'IR_A1');
    }

    protected function includesComponentDetails()
    {
        static $includesComponentDetails = null;

        if ($this->getReportId() !== 'IR') {
            throw new \Exception("System Error - Report: includesComponentDetails can only be used for IR");
        }

        if ($includesComponentDetails === null) {
            $attributes = $this->getReportAttributes();
            $includesComponentDetails = isset($attributes['Include_Component_Details']);
        }

        return $includesComponentDetails;
    }

    protected function computeElements()
    {
        $filters = $this->getReportFilters();
        if (! isset($filters['Begin_Date']) || ! isset($filters['End_Date'])) {
            $message = 'Reporting_Period is invalid';
            $this->checkResult->fatalError($message, $message);
        }
        $this->beginDate = $filters['Begin_Date'];
        $this->endDate = $filters['End_Date'];

        $attributes = $this->getReportAttributes();
        $attributesToShow = (isset($attributes['Attributes_To_Show']) ? $attributes['Attributes_To_Show'] : []);

        $elements = $this->config->getReportElements($this->getReportId(), $this->getFormat(), $attributesToShow);

        if ($this->getReportId() === 'IR') {
            if (! $this->includesParentDetails()) {
                foreach (array_keys($elements) as $elementName) {
                    if (strstr($elementName, 'Parent') !== false) {
                        unset($elements[$elementName]);
                    }
                }
            }
            if (! $this->includesComponentDetails()) {
                foreach (array_keys($elements) as $elementName) {
                    if (strstr($elementName, 'Component') !== false) {
                        unset($elements[$elementName]);
                    }
                }
            }
        }

        if ($this->getFormat() === self::FORMAT_TABULAR && ! isset($attributes['Exclude_Monthly_Details'])) {
            $month = \DateTime::createFromFormat('Y-m-d', $filters['Begin_Date']);
            while ($month->format('Y-m-t') <= $filters['End_Date']) {
                $elements[$month->format('M-Y')] = [
                    'parse' => 'parseMonthlyData'
                ];
                $month->modify('+1 month');
            }
        }

        $this->elements = $elements;
    }

    protected function computeValues()
    {
        $values = $this->getReportFilters();
        foreach ($this->config->getReportFilters($this->getReportId(), false) as $filterName => $filterConfig) {
            if (! isset($values[$filterName]) && isset($filterConfig['values'])) {
                $values[$filterName] = $filterConfig['values'];
            }
        }

        $this->values = $values;
    }

    protected function hasMetricType($element, $regex)
    {
        if (! isset($element['Performance'])) {
            return false;
        }
        foreach (array_keys($element['Performance']) as $metricType) {
            if (preg_match($regex, $metricType)) {
                return true;
            }
        }
        return false;
    }

    protected function getPerformanceByDate($performance)
    {
        $performanceByDate = [];
        foreach ($performance as $metricType => $dateCounts) {
            foreach ($dateCounts as $date => $count) {
                if (! isset($performanceByDate[$date])) {
                    $performanceByDate[$date] = [
                        $metricType => $count
                    ];
                } else {
                    $performanceByDate[$date][$metricType] = $count;
                }
            }
        }
        return $performanceByDate;
    }

    protected function computeHash($elements, $ignoreSectionType = false)
    {
        $hashContext = hash_init('sha256');
        $this->updateHash($hashContext, $elements, $ignoreSectionType);
        return hash_final($hashContext);
    }

    protected function updateHash($hashContext, $elements, $ignoreSectionType, $position = null)
    {
        ksort($elements);
        foreach ($elements as $element => $value) {
            $element = (string) $element;
            if (in_array($element, [
                'Item_Component',
                'Item_Parent',
                'Performance',
                'Positions'
            ])) {
                continue;
            }
            if ($ignoreSectionType && $element === 'Section_Type') {
                continue;
            }
            if (is_object($value)) {
                $value = (array) $value;
            }
            if (is_array($value)) {
                $this->updateHash($hashContext, $value, $ignoreSectionType,
                    ($position === null ? $element : $position . '.' . $element));
            } else {
                $string = ($position === null ? $element : $position . '.' . $element) . ' => ' . $value;
                hash_update($hashContext, mb_strtolower($string));
            }
        }
    }

    protected function debugItem($position)
    {
        if (! isset($GLOBALS['debugItems']) || $GLOBALS['debugItems'] !== true) {
            return;
        }

        print("Item ($position) ");
        print_r($this->currentItem);
        if (! empty($this->currentParent)) {
            print("Parent ($position) ");
            print_r($this->currentParent);
        }
        if (! empty($this->currentComponents)) {
            print("Components ($position) ");
            print_r($this->currentComponents);
        }
    }

    public function getCheckResultAsSpreadsheet($summaryThreshold = 500)
    {
        $spreadsheet = $this->checkResult->asSpreadsheet($summaryThreshold);

        $institutionName = $this->getInstitutionName() ?? '(Institution_Name missing)';
        $created = $this->getCreated() ?? '(Created missing or invalid)';
        $createdBy = $this->getCreatedBy() ?? '(Created_By missing)';
        $beginDate = $this->getBeginDate() ?? '(Begin_Date missing or invalid)';
        $endDate = $this->getEndDate() ?? '(End_Date missing or invalid)';
        $headers = [
            'Validation Result for COUNTER Release ' . $this->getRelease() . ' Report',
            '',
            $this->getReportName() . ' (' . $this->getReportId() . ')',
            'for ' . $institutionName,
            'created ' . $created . ' by ' . $createdBy,
            'covering ' . $beginDate . ' to ' . $endDate,
            '(please see the Report Header sheet for details)'
        ];
        $row = 1;
        $resultsheet = $spreadsheet->getActiveSheet();
        $resultsheet->insertNewRowBefore($row, count($headers) + 1);
        foreach ($headers as $header) {
            $resultsheet->mergeCells("A{$row}:D{$row}");
            $resultsheet->setCellValue("A{$row}", $header);
            $row ++;
        }

        $headersheet = new Worksheet();
        $headersheet->setTitle('Report Header');
        $spreadsheet->addSheet($headersheet);
        $this->originalReportHeadersAsWorksheet($headersheet);

        return $spreadsheet;
    }
}
