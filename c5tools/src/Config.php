<?php
namespace ubfr\c5tools;

abstract class Config
{

    protected $headers;

    protected $identifiers;

    protected $attributes;

    protected $elements;

    protected $reports;

    public static function forRelease($release)
    {
        if ($release !== '5') {
            throw new \Exception("System Error - Config: COUNTER Release {$release} invalid/unsupported");
        }
        return new R5Config();
    }

    protected function readConfig($configDir)
    {
        $this->readHeaders($configDir);
        $this->readIdentifiers($configDir);
        $this->readAttributes($configDir);
        $this->readElements($configDir);
        $this->readReports($configDir . DIRECTORY_SEPARATOR . 'reports');
    }

    protected function readHeaders($configDir)
    {
        $filename = $configDir . DIRECTORY_SEPARATOR . 'headers.php';
        $this->headers = require ($filename);
        foreach ($this->headers as $headerName => $headerConfig) {
            if (! isset($headerConfig['parse']) && ! isset($headerConfig['check'])) {
                throw new \Exception("System Error - Config: parse/check missing for header {$headerName}");
            }
            if (isset($headerConfig['parse']) && isset($headerConfig['check'])) {
                throw new \Exception("System Error - Config: both parse/check present for header {$headerName}");
            }
        }
    }

    protected function readIdentifiers($configDir)
    {
        $filename = $configDir . DIRECTORY_SEPARATOR . 'identifiers.php';
        $this->identifiers = require ($filename);
        foreach ($this->identifiers as $objectName => $identifierTypes) {
            foreach ($identifierTypes as $typeName => $typeConfig) {
                if (! isset($typeConfig['check'])) {
                    throw new \Exception(
                        "System Error - Config: check missing for identifier {$objectName}/{$typeName}");
                }
            }
        }
    }

    protected function readAttributes($configDir)
    {
        $filename = $configDir . DIRECTORY_SEPARATOR . 'attributes.php';
        $this->attributes = require ($filename);
    }

    protected function readElements($configDir)
    {
        $filename = $configDir . DIRECTORY_SEPARATOR . 'elements.php';
        $this->elements = require ($filename);
    }

    protected function readReports($reportsDir)
    {
        $this->reports = [];
        foreach (new \DirectoryIterator($reportsDir) as $finfo) {
            if (! $finfo->isDot()) {
                $filename = $reportsDir . DIRECTORY_SEPARATOR . $finfo->getFilename();
                $report = require ($filename);
                if (! isset($report['ID'])) {
                    throw new \Exception("System Error - Config: ID missing in {$filename}");
                }
                if (isset($report['MasterReport']) === isset($report['Attributes'])) {
                    throw new \Exception('System Error - Config: either MasterReport or Attributes must be present');
                }
                $id = $report['ID'];
                unset($report['ID']);
                if (isset($this->reports[$id])) {
                    throw new \Exception("System Error - Config: duplicate ID '{$id}' in {$filename}");
                }
                $this->reports[$id] = $report;
            }
        }
    }

    public function isMasterReport($reportId)
    {
        if (! isset($this->reports[$reportId])) {
            throw new \Exception("System Error - Config: Report_ID {$reportId} invalid");
        }
        return ! isset($this->reports[$reportId]['MasterReport']);
    }

    public function getReportHeaders($format)
    {
        if ($format !== Report::FORMAT_JSON && $format !== Report::FORMAT_TABULAR) {
            throw new \Exception("System Error - Config: report format {$format} invalid");
        }

        $headers = [];
        foreach ($this->headers as $headerName => $headerConfig) {
            if ($format === Report::FORMAT_JSON) {
                if (! isset($headerConfig[Report::FORMAT_JSON])) {
                    continue;
                }
                $header = [
                    'required' => ($headerConfig[Report::FORMAT_JSON] === 'required')
                ];
            } else {
                if (! isset($headerConfig['row'])) {
                    continue;
                }
                $row = $headerConfig['row'];
                if (! is_int($row) || $row < 1 || $row > 13) {
                    throw new \Exception("System Error - Config: row number {$row} invalid");
                }
                $header = [
                    'row' => $headerConfig['row']
                ];
            }
            if (isset($headerConfig['parse'])) {
                $header['parse'] = $headerConfig['parse'];
            }
            if (isset($headerConfig['check'])) {
                $header['check'] = $headerConfig['check'];
            }
            $headers[$headerName] = $header;
        }

        return $headers;
    }

    public function getReportIds()
    {
        return array_keys($this->reports);
    }

    public function getReportName($reportId)
    {
        if (! isset($this->reports[$reportId])) {
            throw new \Exception("System Error - Config: Report_ID {$reportId} invalid");
        }
        if (! isset($this->reports[$reportId]['Name'])) {
            throw new \Exception("System Error - Config: Name missing for report {$reportId}");
        }
        return $this->reports[$reportId]['Name'];
    }

    public function getIdentifiers($object, $format)
    {
        if (! isset($this->identifiers[$object])) {
            throw new \Exception("System Error - Config: no identifiers for {$object}");
        }

        $identifiers = [];
        foreach ($this->identifiers[$object] as $identifierName => $identifierConfig) {
            if (! isset($identifierConfig[$format]) || $identifierConfig[$format] !== false) {
                $identifiers[$identifierName] = $identifierConfig;
            }
        }
        return $identifiers;
    }

    public function getReportAttributes($reportId, $format)
    {
        if (! isset($this->reports[$reportId])) {
            throw new \Exception("System Error - Config: Report_ID {$reportId} invalid");
        }
        if ($format !== Report::FORMAT_JSON && $format !== Report::FORMAT_TABULAR) {
            throw new \Exception("System Error - Config: report format {$format} invalid");
        }
        if (! isset($this->reports[$reportId]['Attributes'])) {
            throw new \Exception("System Error - Config: no report attributes");
        }

        $attributes = [];
        foreach ($this->reports[$reportId]['Attributes'] as $key => $value) {
            if (is_array($value)) {
                $attributeName = $key;
                $attributeConfig = [
                    'values' => $value
                ];
            } else {
                $attributeName = $value;
                $attributeConfig = [];
            }
            if (! (isset($this->attributes[$attributeName]))) {
                throw new \Exception("System Error - Config: Attribute name {$attributeName} invalid");
            }
            if (! isset($this->attributes[$attributeName][$format]) ||
                $this->attributes[$attributeName][$format] === true) {
                $attributes[$attributeName] = array_merge($attributeConfig, $this->attributes[$attributeName]);
            }
        }

        return $attributes;
    }

    public function isAttributesToShow($reportId, $format, $element)
    {
        if (! $this->isMasterReport($reportId)) {
            return false;
        }
        $reportAttributes = $this->getReportAttributes($reportId, $format);
        if (! isset($reportAttributes['Attributes_To_Show']) ||
            ! isset($reportAttributes['Attributes_To_Show']['values'])) {
            throw new \Exception("System Error - Config: Attributes_To_Show or values missing");
        }
        return in_array($element, $reportAttributes['Attributes_To_Show']['values']);
    }

    public function getReportFilters($reportId, $excludeDefaultsForStandardView = true)
    {
        if (! isset($this->reports[$reportId])) {
            throw new \Exception("System Error - Config: Report_ID {$reportId} invalid");
        }

        $filters = [];
        foreach ($this->reports[$reportId]['Filters'] as $key => $value) {
            if (is_array($value)) {
                $filterName = $key;
                $filterConfig = [
                    'multi' => true,
                    'values' => $value
                ];
                if ($this->isMasterReport($reportId)) {
                    $filterConfig['default'] = 'All';
                } else {
                    $masterReportId = $this->reports[$reportId]['MasterReport'];
                    if (! isset($this->reports[$masterReportId])) {
                        throw new \Exception("System Error - Config: MasterReport {$masterReportId} invalid");
                    }
                    $masterReportFilters = $this->reports[$masterReportId]['Filters'];
                    if (! isset($masterReportFilters[$filterName])) {
                        throw new \Exception(
                            "System Error - Config: Filter {$filterName} missing for {$masterReportId}");
                    }
                    $masterReportValue = $masterReportFilters[$filterName];
                    if (array_diff($value, $masterReportValue) === array_diff($masterReportValue, $value)) {
                        if ($excludeDefaultsForStandardView) {
                            continue;
                        } else {
                            $filterConfig['default'] = 'All';
                        }
                    }
                }
            } else {
                $filterName = $value;
                if ($filterName === 'YOP') {
                    $filterConfig = [
                        'multi' => true
                    ];
                } else {
                    $filterConfig = [];
                }
            }
            $filters[$filterName] = $filterConfig;
        }
        
        return $filters;
    }

    public function getReportElements($reportId, $format, $attributesToShow)
    {
        if (! isset($this->reports[$reportId])) {
            throw new \Exception("System Error - Config: Report_ID {$reportId} invalid");
        }
        if (! isset($this->reports[$reportId]['Elements'])) {
            throw new \Exception("System Error - Config: {$reportId} elements missing");
        }
        if ($format !== Report::FORMAT_JSON && $format !== Report::FORMAT_TABULAR) {
            throw new \Exception("System Error - Config: report format {$format} invalid");
        }
        if (! isset($this->elements[$format])) {
            throw new \Exception("System Error - Config: {$format} elements missing");
        }

        $elements = [];
        foreach ($this->elements[$format] as $elementName => $elementConfig) {
            if (! isset($elementConfig['parse'])) {
                throw new \Exception("System Error - Config: parser missing for {$format} element {$elementName}");
            }
            if (in_array($elementName, $this->reports[$reportId]['Elements']) ||
                in_array($elementName, $attributesToShow)) {
                $elements[$elementName] = [
                    'parse' => $elementConfig['parse']
                ];
                if ($format === Report::FORMAT_JSON) {
                    $elements[$elementName]['required'] = $elementConfig['required'] ?? false;
                    if (isset($elementConfig['elements'])) {
                        // element with subelements, currently Item_Parent and Item_Component only
                        $elements[$elementName]['elements'] = [];
                        foreach ($elementConfig['elements'] as $subElementName => $subElementConfig) {
                            if (! isset($subElementConfig['parse'])) {
                                throw new \Exception(
                                    "System Error - Config: parser missing for JSON element {$elementName}.{$subElementName}");
                            }
                            if (isset($this->reports[$reportId][$elementName])) {
                                // subelements in configuration are used as filters, currently IT_A1/Item_Parent only
                                $elements[$elementName]['required'] = true;
                                if (! in_array($subElementName, $this->reports[$reportId][$elementName])) {
                                    continue;
                                }
                            }
                            $elements[$elementName]['elements'][$subElementName] = [
                                'parse' => $subElementConfig['parse']
                            ];
                            $elements[$elementName]['elements'][$subElementName]['required'] = $subElementConfig['required'] ?? false;
                        }
                    }
                }
            }
        }

        return $elements;
    }
}
