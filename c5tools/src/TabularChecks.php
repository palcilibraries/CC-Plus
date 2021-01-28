<?php
namespace ubfr\c5tools;

trait TabularChecks
{

    protected function checkedOrganizationIdentifiers($position, $element, $identifiers, $permittedIdentifiers)
    {
        if (($identifiers = $this->checkedIsString($position, $element, $identifiers)) === false) {
            return [];
        }

        $checkedIdentifiers = [];
        $types = [];
        $invalid = [];
        $data = $this->formatData($element, $identifiers);
        $typeValues = $this->getSpaceSemicolonSeparatedValues($position, $element, $identifiers);
        foreach ($typeValues as $typeValue) {
            if ($typeValue === '') {
                $message = "{$element} must not be empty";
                $this->checkResult->addError($message, $message, $position, $data);
                continue;
            }
            $parts = explode(':', $typeValue, 2);
            if (count($parts) === 1 || $parts[0] === '') {
                $message = "Namespace is missing for {$element}";
                $this->checkResult->addError($message, $message . " value '{$typeValue}'", $position, $data,
                    "format of {$element} must be {namespace}:{value}");
                $invalid[] = $typeValue;
                continue;
            }
            $type = $parts[0];
            $value = $parts[1];
            $correctType = $this->inArrayLax($type, $permittedIdentifiers);
            if ($correctType === false) {
                $type = 'Proprietary';
                $value = $typeValue;
            } else {
                if ($type !== $correctType) {
                    $this->checkResult->addError("Spelling of {$element} value is wrong",
                        "Spelling of {$element} value '{$type}' is wrong", $position, $data,
                        "must be spelled '{$correctType}'");
                    $type = $correctType;
                }
                if ($type === 'Proprietary') {
                    $message = 'Namespace Proprietary must be omitted for tabular reports';
                    $this->checkResult->addError($message, $message, $position, $data);
                    if (count($parts) !== 3) {
                        $message = "Namespace is missing for {$element}";
                        $this->checkResult->addError($message, $message . " value '{$value}'", $position, $data,
                            "format of {$element} must be {namespace}:{value}");
                        $invalid[] = $value;
                        continue;
                    }
                }
            }
            if (isset($types[$type])) {
                $message = "Identifier {$type} specified multiple times";
                $this->checkResult->addError($message, $message, $position, $data,
                    "in tabular reports only one identifier per type is permitted, ignoring all but the first {$type} value");
                $invalid[] = $typeValue;
                continue;
            }
            $exploded = explode('|', $value);
            if (count($exploded) > 1) {
                $message = "Found multiple identifier {$type} values separated by pipe character";
                $this->checkResult->addError($message, $message, $position, $data,
                    "in tabular reports only one identifier per type is permitted, ignoring all but the first {$type} value");
                $value = reset($exploded);
                $invalid[] = ($type === 'Proprietary' ? '' : "{$type}:") . implode('|', array_slice($exploded, 1));
            }
            $checkedIdentifiers[] = (object) [
                'Type' => $type,
                'Value' => $value
            ];
            $types[$type] = true;
        }
        if (! empty($invalid)) {
            $this->addInvalid('Item', $element, implode('; ', $invalid));
        }

        return $checkedIdentifiers;
    }

    protected function checkRequiredParentComponentColumns($rowNumber)
    {
        foreach ([
            'Parent',
            'Component'
        ] as $context) {
            $current = 'current' . $context;
            if (! empty($this->$current)) {
                if (! isset($this->$current['Item_Name']) &&
                    ($columnName = $this->getColumnForElement($context . '_Title')) !== false) {
                    $elementName = $context . '_Title';
                    $data = $this->formatData($elementName, '');
                    $position = $columnName . $rowNumber;
                    $message = "{$elementName} must not be empty";
                    $this->checkResult->addCriticalError($message, $message, $position, $data);
                }
                if (! isset($this->$current['Data_Type']) &&
                    ($columnName = $this->getColumnForElement($context . '_Data_Type')) !== false) {
                    $elementName = $context . '_Data_Type';
                    $data = $this->formatData($elementName, '');
                    $position = $columnName . $rowNumber;
                    $message = "{$elementName} must not be empty";
                    $this->checkResult->addCriticalError($message, $message, $position, $data);
                }
            }
        }
    }

    protected function checkSectionType($position)
    {
        if (isset($this->currentItem['Metric_Type'])) {
            // Metric_Type is present and valid, so Section_Type can be checked against Metric_Type
            if (preg_match('/^Unique_Title_/', $this->currentItem['Metric_Type'])) {
                // Section_Type must be empty for Unique_Title metrics
                if (isset($this->currentItem['Section_Type'])) {
                    $message = "Section_Type must be empty for Unique_Title metrics";
                    $data = $this->formatData('Section_Type', $this->currentItem['Section_Type']);
                    $this->checkResult->addCriticalError($message,
                        "{$message}, found '" . $this->currentItem['Section_Type'] . "'", $position, $data);
                    $this->addInvalid('Item', 'Section_Type', $this->currentItem['Section_Type']);
                    unset($this->currentItem['Section_Type']);
                    return;
                }
            } else {
                // Section_Type must not be empty for other metrics
                if (! isset($this->currentItem['Section_Type']) && ! isset(
                    $this->currentItem['Invalid']['Section_Type'])) {
                    $message = 'Section_Type must not be empty';
                    $this->checkResult->addCriticalError($message, $message, $position,
                        $this->formatData('Section_Type', ''));
                    return;
                }
            }
        }

        parent::checkSectionType($position);
    }

    protected function checkReportingPeriodTotal($rowNumber)
    {
        if ($this->reportingPeriodTotalColumn === null) {
            return;
        }
        if (isset($this->currentItem['Reporting_Period_Total'])) {
            if (isset($this->currentItem['Counts'])) {
                $reportingPeriodTotal = $this->currentItem['Reporting_Period_Total'];
                $sumOfCounts = array_sum($this->currentItem['Counts']);
                if ($reportingPeriodTotal !== $sumOfCounts) {
                    $data = $this->formatData('Reporting_Period_Total', $reportingPeriodTotal);
                    $message = 'Reporting_Period_Total differs from sum of monthly counts';
                    $this->checkResult->addCriticalError($message,
                        $message . " ({$reportingPeriodTotal} vs. {$sumOfCounts})",
                        $this->reportingPeriodTotalColumn . $rowNumber, $data);
                }
            } elseif ($this->getGranularity() === self::GRANULARITY_TOTALS) {
                static $beginPeriod = null;

                if ($beginPeriod === null) {
                    $filters = $this->getReportFilters();
                    $datetime = \DateTime::createFromFormat('Y-m-d', $filters['Begin_Date']);
                    $beginPeriod = $datetime->format('Y-m');
                }

                $this->currentItem['Counts'] = [
                    $beginPeriod => $this->currentItem['Reporting_Period_Total']
                ];
            }
            unset($this->currentItem['Reporting_Period_Total']);
        }
    }
}
