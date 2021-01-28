<?php
namespace ubfr\c5tools;

trait TabularParsers
{

    protected $numberOfRows;

    protected $invalidColumns;

    protected function parse()
    {
        $sheetCount = $this->spreadsheet->getSheetCount();
        if ($sheetCount > 1) {
            $message = 'Spreadsheet must contain only one sheet';
            $this->checkResult->addError($message, $message . ", found {$sheetCount} sheets", null, null,
                'only the active sheet will be checked');
        }
        $sheet = $this->spreadsheet->getActiveSheet();
        $this->numberOfRows = $sheet->getHighestRow();
        $this->invalidColumns = [];

        $this->parseHeader($sheet);

        try {
            $this->computeElements();
            $this->computeValues();

            $this->parseBody($sheet);
            $this->checkMetrics();
        } catch (ParseException $e) {
            // ignore ParseException, error message is part of CheckResult
        }
    }

    protected function parseHeader($sheet)
    {
        $headerRows = [];
        foreach ($sheet->getRowIterator(1, min($this->numberOfRows, 13)) as $row) {
            $headerRows[$row->getRowIndex()] = $this->getRowValues($row);
        }
        for ($rowNumber = $this->numberOfRows + 1; $rowNumber <= 13; $rowNumber ++) {
            $headerRows[$rowNumber] = [];
        }

        foreach ($this->config->getReportHeaders($this->getFormat()) as $headerName => $headerConfig) {
            $rowNumber = $headerConfig['row'];

            if (! isset($headerRows[$rowNumber]['A'])) {
                $message = 'Header label is missing';
                $this->checkResult->addCriticalError($message, $message, 'A' . $rowNumber, null);
                continue;
            } else {
                $headerLabel = $headerRows[$rowNumber]['A'];
                $position = 'A' . $rowNumber;
                $this->tabularHeaders[$position] = $headerLabel;
                if ($headerLabel !== $headerName) {
                    if ($this->lax($headerLabel) === $this->lax($headerName)) {
                        $this->checkResult->addError('Spelling of header label is wrong',
                            "Spelling of header label '{$headerLabel}' is wrong", $position, $headerLabel,
                            "must be spelled '{$headerName}'");
                    } else {
                        $this->checkResult->addCriticalError('Header label is invalid',
                            "Header label '{$headerLabel}' is invalid", $position, $headerLabel,
                            "must be '{$headerName}'");
                        continue;
                    }
                }
            }

            if (! isset($headerRows[$rowNumber]['B'])) {
                $value = '';
            } else {
                $value = $headerRows[$rowNumber]['B'];
                // TODO: type check, currently not possible because numbers formatted as text are treated as numbers
            }
            $this->tabularHeaders["B{$rowNumber}"] = $value;

            if (count($headerRows[$rowNumber]) > 2) {
                foreach (array_slice($headerRows[$rowNumber], 2, null, true) as $columnNumber => $columnValue) {
                    if (trim($columnValue) !== '') {
                        $message = 'Cell must be empty';
                        $position = $columnNumber . $rowNumber;
                        $this->checkResult->addError($message, $message . ", found '{$columnValue}'", $position,
                            $columnValue);
                    }
                }
            }

            if (isset($headerConfig['parse'])) {
                $parse = $headerConfig['parse'];
                $this->$parse("B{$rowNumber}", $value);
            } else {
                $check = $headerConfig['check'];
                $result = $this->$check("B{$rowNumber}", $headerName, $value);
                if ($result !== false) {
                    $this->headers[$headerName] = $result;
                }
            }
        }

        foreach ($headerRows[13] as $columnNumber => $columnValue) {
            $position = $columnNumber . '13';
            if (trim($columnValue) !== '') {
                $this->checkResult->addCriticalError('Cell must be empty',
                    "Cell {$position} must be empty, found '{$columnValue}'", $position, $columnValue,
                    "row 13 must be empty in tabular reports");
            }
        }
    }

    protected function parseExceptions($position, $exceptions)
    {
        // TODO
    }

    protected function parseInstitutionIds($position, $institutionId)
    {
        $institutionIds = $this->checkedOrganizationIdentifiers($position, 'Institution_ID', $institutionId,
            array_keys($this->config->getIdentifiers('Institution', $this->getFormat())));
        if (! empty($institutionIds)) {
            parent::parseInstitutionIds($position, $institutionIds);
        }
    }

    protected function parseMetricTypes($position, $metricTypes)
    {
        if ($metricTypes === '') {
            return;
        }
        if (strstr($metricTypes, '|')) {
            $message = 'Metric_Types must be separated by semicolon-space, not pipe';
            $data = $this->formatData('Metric_Types', $metricTypes);
            $this->checkResult->addError($message, $message, $position, $data);
            $metricTypes = implode('; ', explode('|', $metricTypes));
        }
        if (! isset($this->filters['Metric_Type'])) {
            $this->filters['Metric_Type'] = [];
        }
        $this->filters['Metric_Type'][] = [
            'v' => implode('|', $this->getSpaceSemicolonSeparatedValues($position, 'Metric_Types', $metricTypes)),
            'p' => $position
        ];
    }

    protected function parseReportAttributes($position, $attributes)
    {
        if ($attributes === '') {
            return;
        }

        if (! $this->isMasterReport()) {
            $data = $this->formatData('Report_Attributes', $attributes);
            $message = 'Report_Attributes must be empty for Standard Views';
            $this->checkResult->addError($message, $message, $position, $data);
            return;
        }

        $permittedAttributes = array_keys($this->config->getReportAttributes($this->getReportId(), $this->getFormat()));
        $attributes = $this->getReportAttributeFilters($position, 'Report_Attributes', $attributes, $permittedAttributes,
            'attribute');
        if (! empty($attributes)) {
            parent::parseReportAttributes($position, $attributes);
        }
    }

    protected function parseReportFilters($position, $filters)
    {
        $permittedFilters = array_keys($this->config->getReportFilters($this->getReportId()));
        $filters = $this->getReportAttributeFilters($position, 'Report_Filters', $filters, $permittedFilters, 'filter');
        $specialFilters = [
            'Begin_Date' => 'Reporting_Period',
            'End_Date' => 'Reporting_Period',
            'Metric_Type' => 'Metric_Types'
        ];
        foreach ($filters as $filterName => $filterValuePositions) {
            if (isset($specialFilters[$filterName])) {
                $message = "{$filterName} is not permitted in Report_Filters";
                // getReportAttributeFilters always returns an array with a single entry
                $data = $this->formatData($filterName, $filterValuePositions[0]['v']);
                $hint = "in tabular reports the {$filterName} filter must be in the {$specialFilters[$filterName]} header";
                if (! isset($this->filters[$filterName])) {
                    $this->filters[$filterName] = $filterValuePositions;
                } else {
                    $hint .= ", ignoring {$filterName} filter";
                }
                $this->checkResult->addError($message, $message, $position, $data, $hint);
            } else {
                $this->filters[$filterName] = $filterValuePositions;
            }
        }

        // now all filter have been colleted in $this->filters, so the values can be parsed and checked
        parent::parseReportFilters($position, $this->filters);
    }

    protected function parseReportingPeriod($position, $reportingPeriod)
    {
        if (trim($reportingPeriod) === '') {
            $message = 'Reporting_Period must not be empty';
            $data = $this->formatData('Reporting_Period', '');
            $this->checkResult->addCriticalError($message, $message, $position, $data);
            return;
        }

        $oldFormat = '/^\s*([0-9]{4}-[0-9]{2}(?:-[0-9]{2})?)\s+to\s+([0-9]{4}-[0-9]{2}(?:-[0-9]{2})?)\s*$/';
        $matches = [];
        $data = $this->formatData('Reporting_Period', $reportingPeriod);
        if (preg_match($oldFormat, $reportingPeriod, $matches)) {
            $message = 'Format is wrong for Reporting_Period';
            $this->checkResult->addError($message, $message, $position, $data,
                "format must be 'Begin_Date=yyyy-mm-dd; End_Date=yyyy-mm-dd'");
            $reportingPeriod = 'Begin_Date=' . $matches[1] . '; End_Date=' . $matches[2];
        }

        $permittedFilters = [
            'Begin_Date',
            'End_Date'
        ];
        $filters = $this->getReportAttributeFilters($position, 'Reporting_Period', $reportingPeriod, $permittedFilters,
            'filter');
        foreach ($permittedFilters as $filter) {
            if (! isset($filters[$filter])) {
                $message = "{$filter} is missing from Reporting_Period";
                $this->checkResult->addCriticalError($message, $message, $position, $data);
            } else {
                $this->filters[$filter] = $filters[$filter];
            }
        }
    }

    protected function getSpaceSemicolonSeparatedValues($position, $element, $string)
    {
        if ($string === '') {
            return [];
        }

        $values = [];
        $data = $this->formatData($element, $string);
        foreach (explode(';', $string) as $index => $part) {
            if ($part === '') {
                $message = "Invalid ';', either at the beginning/end or two consecutive semicolons";
                $this->checkResult->addError($message, $message, $position, $data);
                continue;
            }
            if ($index !== 0) {
                if (substr($part, 0, 1) !== ' ') {
                    $this->checkResult->addError("Space after ';' is missing",
                        "Space between ';' and '{$part}' is missing", $position, $data);
                } else {
                    $part = substr($part, 1);
                }
            }
            if (trim($part) !== $part) {
                if (trim($part) !== '') {
                    $this->checkResult->addError('Value in semicolon-space separted list includes whitespace',
                        "'{$part}' includes whitespace", $position, $data);
                }
                $part = trim($part);
            }
            $values[] = $part;
        }
        return $values;
    }

    protected function getRowValues(&$row)
    {
        $values = [];
        foreach ($row->getCellIterator() as $cell) {
            $values[$cell->getColumn()] = $cell->getFormattedValue();
        }
        while (($lastValue = end($values)) !== false) {
            if (trim($lastValue) !== '') {
                break;
            }
            array_pop($values);
        }
        return $values;
    }

    protected function getReportAttributeFilters($position, $element, $string, $permittedAttributeFilters, $type)
    {
        $attributeFilters = [];
        $data = $this->formatData($element, $string);
        foreach ($this->getSpaceSemicolonSeparatedValues($position, $element, $string) as $keyValue) {
            if ($keyValue === '') {
                $message = ucfirst($type) . ' must not be empty';
                $this->checkResult->addError($message, $message, $position, $data);
                continue;
            }
            $parts = explode('=', $keyValue, 2);
            if (count($parts) === 1 || $parts[0] === '') {
                $message = "Name is missing for {$type}";
                $this->checkResult->addCriticalError($message, $message . " value '{$keyValue}'", $position, $data,
                    "format must be {{$type} name}={{$type} value}");
                continue;
            }
            foreach ($parts as $part) {
                if (trim($part) !== $part) {
                    $this->checkResult->addError(ucfirst($type) . ' value includes whitespace',
                        ucfirst($type) . " value '{$part}' includes whitespace", $position, $data);
                }
            }
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            if (! in_array($name, $permittedAttributeFilters)) {
                if (($correctName = $this->inArrayLax($name, $permittedAttributeFilters)) !== false) {
                    $this->checkResult->addError("Spelling of {$type} name is wrong",
                        "Spelling of {$type} name '{$name}' is wrong", $position, $data,
                        "must be spelled '{$correctName}'");
                    $name = $correctName;
                } else {
                    $this->checkResult->addCriticalError(ucfirst($type) . ' name is invalid',
                        ucfirst($type) . " name '{$name}' is invalid", $position, $data,
                        "permitted {$type}s are '" . implode("', '", $permittedAttributeFilters) . "'");
                    continue;
                }
            }
            if (isset($attributeFilters[$name])) {
                $this->checkResult->addError(ucfirst($type) . " name specified multiple times",
                    ucfirst($type) . " name '{$name}' specified multiple times", $position, $data,
                    'multiple values must be separated by a pipe character ("|")');
                $attributeFilters[$name][0]['v'] .= "|{$value}";
            } else {
                $attributeFilters[$name] = [
                    [
                        'v' => $value,
                        'p' => $position
                    ]
                ];
            }
        }
        return $attributeFilters;
    }

    protected function parseBody(&$sheet)
    {
        if ($this->numberOfRows < 14) {
            $message = 'Report body is missing';
            $this->checkResult->fatalError($message, $message);
        }

        $this->parseColumnHeadings($sheet->getRowIterator(14, 14)
            ->current());

        if (empty($this->columnMap) || $this->numberOfRows < 15) {
            return;
        }

        foreach ($sheet->getRowIterator(15) as $row) {
            $this->parseBodyRow($row);
        }
    }

    protected function parseColumnHeadings($row14)
    {
        $columnMap = [];
        $expectedColumnHeadings = array_keys($this->elements);

        // check if the columns are valid and spelled correctly
        foreach ($this->getRowValues($row14) as $columnNumber => $columnHeading) {
            $position = $columnNumber . '14';
            $data = $this->formatData('Column heading', $columnHeading);
            if (! isset($this->elements[$columnHeading])) {
                if (($correctColumnHeading = $this->inArrayLax($columnHeading, $expectedColumnHeadings)) !== false) {
                    $this->checkResult->addError("Spelling of column heading is wrong",
                        "Spelling of column heading '{$columnHeading}' is wrong", $position, $data,
                        "must be spelled '{$correctColumnHeading}'");
                    $columnHeading = $correctColumnHeading;
                } elseif (($correctColumnHeading = $this->inArrayLaxMonthly($columnHeading, $expectedColumnHeadings)) !==
                    false) {
                    if ($this->isExcelDate($columnHeading)) {
                        $message = "Could not check the date format used for the column heading for monthly counts";
                        $this->checkResult->addWarning($message, $message . " (value '{$columnHeading}')", $position,
                            $data,
                            "using date formats is not recommended because the result might depend " .
                            "on the operating system and spreadsheet application settings");
                    } else {
                        $message = "Format is wrong for column heading";
                        $this->checkResult->addError($message, $message, $position, $data,
                            'the column headings for the monthly counts must be in Mmm-yyyy format');
                    }
                    $columnHeading = $correctColumnHeading;
                } else {
                    if ($this->config->isAttributesToShow($this->getReportId(), $this->getFormat(), $columnHeading)) {
                        $message = 'is not included in Attributes_To_Show and therefore invalid for this report, ignoring column';
                        $addLevel = 'addError';
                    } else {
                        $message = 'is invalid for this report, ignoring column';
                        $addLevel = 'addCriticalError';
                    }
                    $this->checkResult->$addLevel("Column heading {$message}",
                        "Column heading '{$columnHeading}' {$message} {$columnNumber}", $position, $data);
                    $this->invalidColumns[$columnNumber] = $columnHeading;
                    continue;
                }
            }
            if (isset($columnMap[$columnHeading])) {
                $this->checkResult->addCriticalError('Duplicate column heading, ignoring column',
                    "Duplicate column heading {$columnHeading}, ignoring column {$columnNumber}", $position,
                    $columnHeading);
                $this->invalidColumns[$columnNumber] = $columnHeading;
                continue;
            }
            $columnMap[$columnHeading] = [
                'columnNumber' => $columnNumber,
                'parse' => $this->elements[$columnHeading]['parse']
            ];
        }

        // check for missing columns or wrong column order
        $foundColumnHeadings = [];
        $missingColumnHeadings = [];
        foreach ($expectedColumnHeadings as $columnHeading) {
            if (isset($columnMap[$columnHeading])) {
                $foundColumnHeadings[] = $columnHeading;
            } else {
                $missingColumnHeadings[] = $columnHeading;
            }
        }
        if (! empty($missingColumnHeadings)) {
            $message = 'Columns required for this report are missing';
            $this->checkResult->addCriticalError($message,
                $message . ": '" . implode("', '", $missingColumnHeadings) . "'", '14', '');
        }
        $columnMapHeadings = array_keys($columnMap);
        if ($foundColumnHeadings !== $columnMapHeadings) {
            foreach ($columnMapHeadings as $index => $columnMapHeading) {
                if ($columnMapHeading === $foundColumnHeadings[$index]) {
                    unset($columnMapHeadings[$index]);
                    unset($foundColumnHeadings[$index]);
                }
            }
            $this->checkResult->addCriticalError('Order of columns is wrong',
                "Order of columns '" . implode("', '", $columnMapHeadings) . "' is wrong, expecting order '" .
                implode("', '", $foundColumnHeadings) . "'", '14', '');
        }

        // create final colum map with all correct(ed) columns
        foreach ($columnMap as $columnHeading => $mapInfo) {
            $this->columnMap[$mapInfo['columnNumber']] = [
                'columnHeading' => $columnHeading,
                'parse' => $mapInfo['parse']
            ];
            if ($columnHeading === 'Reporting_Period_Total') {
                $this->reportingPeriodTotalColumn = $mapInfo['columnNumber'];
            }
        }
    }

    protected function inArrayLaxMonthly($columnHeading, $expectedColumnHeadings)
    {
        $columnHeading = trim($columnHeading);
        $format = null;
        // the current day is automatically added which might result in a wrong month, so we have to add a valid day
        if (preg_match('/^[a-zA-Z]{3}-[0-9]{2}$/', $columnHeading)) {
            $format = 'd-M-y'; // [Mm]mm-yy
            $columnHeading = '01-' . $columnHeading;
        } elseif (preg_match('/^[0-9]{4}-[0-9]{2}$/', $columnHeading)) {
            $format = 'Y-m-d'; // yyyy-mm
            $columnHeading .= '-01';
        } elseif (preg_match('/^[0-9]{4}-[0-9]$/', $columnHeading)) {
            $format = 'Y-n-d'; // yyyy-m
            $columnHeading .= '-01';
        } elseif ($this->isExcelDate($columnHeading)) {
            // Excel
            $columnHeading = ($columnHeading - 25569) * 86400;
            $format = 'U';
        }
        if ($format !== null) {
            $datetime = \DateTime::createFromFormat($format, $columnHeading);
            if ($datetime !== false) {
                $correctColumnHeading = $datetime->format('M-Y');
                if (in_array($correctColumnHeading, $expectedColumnHeadings)) {
                    return $correctColumnHeading;
                }
            }
        }
        return false;
    }

    protected function isExcelDate($string)
    {
        static $isTextFormat = null;

        if ($isTextFormat === null) {
            $isTextFormat = (substr($this->mimetype, 0, 5) === 'text/');
        }

        if ($isTextFormat) {
            return false;
        }
        return (preg_match('/^[0-9]+$/', $string) && $string > 25569);
    }

    protected function parseBodyRow(&$row)
    {
        $this->currentItem = [];
        $this->currentParent = [];
        $this->currentComponent = [];
        $this->currentComponents = [];
        $this->currentSectionTypePosition = null;

        $rowNumber = $row->getRowIndex();
        $rowValues = $this->getRowValues($row);

        // parse valid columns
        foreach ($this->columnMap as $columnNumber => $columnMap) {
            $position = $columnNumber . $rowNumber;
            $columnValue = (isset($rowValues[$columnNumber]) ? $rowValues[$columnNumber] : '');
            $columnHeading = $columnMap['columnHeading'];
            $parse = $columnMap['parse'];
            if (substr($columnHeading, 0, 10) === 'Component_') {
                $element = substr($columnHeading, 10);
                $context = 'Component';
            } elseif (substr($columnHeading, 0, 7) === 'Parent_') {
                $element = substr($columnHeading, 7);
                $context = 'Parent';
            } else {
                $element = $columnHeading;
                // everything else is simply stored in currentItem (including counts)
                $context = 'Item';
            }
            $this->$parse($position, $element, $columnValue, $context);
            $lastColumnNumber = $columnNumber;
        }

        // store non-empty invalid columns excluding monthly columns
        foreach ($this->invalidColumns as $columnNumber => $columnHeading) {
            if ($columnNumber > $lastColumnNumber) {
                continue;
            }
            $columnValue = (isset($rowValues[$columnNumber]) ? $rowValues[$columnNumber] : '');
            if ($columnValue === '') {
                continue;
            }
            $this->addInvalid($this->getContext($columnHeading), $columnHeading, $columnValue);
        }

        // check if there are additional column that should not be present
        if (isset($rowValues[$lastColumnNumber])) {
            reset($rowValues);
            while (key($rowValues) !== $lastColumnNumber) {
                next($rowValues);
            }
            while (next($rowValues) !== false) {
                $columnNumber = key($rowValues);
                if (! isset($this->invalidColumns[$columnNumber])) {
                    $columnValue = current($rowValues);
                    if (trim($columnValue) !== '') {
                        $position = $columnNumber . $rowNumber;
                        $this->checkResult->addError('Cell must be empty',
                            "Cell {$position} must be empty, found '{$columnValue}'", $position, $columnValue);
                    }
                }
                next($rowValues);
            }
        }

        // checks which require correlation between different elements within the row
        $this->checkItemTitleIdentifiers($rowNumber);
        $this->checkRequiredParentComponentColumns($rowNumber);
        if ($this->currentSectionTypePosition !== null) {
            $this->checkSectionType($this->currentSectionTypePosition);
        }
        $this->checkReportingPeriodTotal($rowNumber);

        $this->countsToPerformance();

        if ($this->storeCurrentComponent($rowNumber) === false) {
            // TODO: Handle Unique_Item metrics with Components instead of ignoring them?
            return;
        }
        $this->storeCurrentParent($rowNumber);
        $this->storeCurrentItem($rowNumber);

        $this->debugItem($rowNumber);
    }

    protected function storeCurrentComponent($rowNumber)
    {
        if ($this->getReportId() !== 'IR' || ! $this->includesComponentDetails()) {
            return true;
        }

        if ($this->hasMetricType($this->currentItem, '/Unique_Item_/')) {
            if (! empty($this->currentComponent)) {
                $message = 'Unique_Item metrics cannot be broken down by Component';
                $this->checkResult->addCriticalError($message, $message, $rowNumber, 'Component');
                $this->addInvalid('Item', 'Item_Component', $this->currentComponent);
                return false;
            }
        } else {
            // for Total_Item and Access Denied metrics there must be a Component ...
            if (empty($this->currentComponent)) {
                if ($this->hasMetricType($this->currentItem, '/Total_Item_/')) {
                    $message = 'Component missing for Totel_Item metric';
                    $hint = 'if the Item itself was used it must be repeated in Component';
                } else {
                    $message = 'Component missing for Access Denied metric';
                    $hint = 'if access to the Item itself was denied it must be repeated in Component';
                }
                $this->checkResult->addError($message, $message, $rowNumber, 'Component', $hint);
                $this->currentComponent = $this->copyItemToComponent();
            }
            // ... and the Performance must be stored in the Component
            if (isset($this->currentItem['Performance'])) {
                $this->currentComponent['Performance'] = $this->currentItem['Performance'];
                unset($this->currentItem['Performance']);
            }

            $hash = $this->computeHash($this->currentComponent);
            $this->currentComponents[$hash] = $this->currentComponent;
            $this->currentComponents[$hash]['Positions'] = [
                $rowNumber
            ];

            $this->currentItem['Item_Component'] = $this->currentComponents;
        }
        return true;
    }

    protected function parsePublisherIds($position, $element, $value, $context)
    {
        $publisherIds = $this->checkedOrganizationIdentifiers($position, 'Publisher_ID', $value,
            array_keys($this->config->getIdentifiers('Publisher', $this->getFormat())));
        if (! empty($publisherIds)) {
            parent::parsePublisherIds($position, $element, $publisherIds, $context);
        }
    }

    protected function parseAuthors($position, $element, $value, $context)
    {
        $elementName = $this->getElementName($element, $context);
        $current = 'current' . $context;

        foreach ($this->getSpaceSemicolonSeparatedValues($position, $elementName, $value) as $authorString) {
            $itemContributor = [
                'Type' => 'Author'
            ];
            $matches = [];
            if (preg_match('/^(.+) \(([^:]+:[^)]+)\)$/', $authorString, $matches)) {
                $itemContributor['Name'] = $matches[1];
                $checkedAuthorIdentifier = $this->checkedAuthorIdentifier($position, "{$elementName} identifier",
                    $authorString, $matches[2]);
                if ($checkedAuthorIdentifier !== false) {
                    $itemContributor['Identifier'] = $checkedAuthorIdentifier;
                } else {
                    $this->addInvalid($context, 'Item_Contributors', $authorString);
                    continue;
                }
            } else {
                $itemContributor['Name'] = $authorString;
            }
            if (! isset($this->$current['Item_Contributors'])) {
                $this->$current['Item_Contributors'] = [];
            }
            $this->$current['Item_Contributors'][] = $itemContributor;
        }
    }

    protected function parsePublicationDate($position, $element, $value, $context)
    {
        $elementName = $this->getElementName($element, $context);
        $current = 'current' . $context;

        if (trim($value) !== $value) {
            $data = $this->formatData($elementName, $value);
            $this->checkResult->addError("{$elementName} value includes whitespace",
                "{$elementName} value '{$value}' includes whitespace", $position, $data);
            $value = trim($value);
        }

        if ($value === '') {
            return;
        }

        $checkedDate = $this->checkedDate($position, $elementName, $value);
        if ($checkedDate !== false) {
            $this->$current['Item_Dates'] = [
                $element => $checkedDate
            ];
        } else {
            $this->addInvalid($context, $element, $value);
        }
    }

    protected function parseArticleVersion($position, $element, $value, $context)
    {
        $elementName = $this->getElementName($element, $context);
        $current = 'current' . $context;

        if (trim($value) !== $value) {
            $data = $this->formatData($elementName, $value);
            $this->checkResult->addError("{$elementName} value includes whitespace",
                "{$elementName} value '{$value}' includes whitespace", $position, $data);
            $value = trim($value);
        }

        if ($value === '') {
            return;
        }

        $checkedArticleVersion = $this->checkedArticleVersion($position, $element, $value, $context);
        if ($checkedArticleVersion !== false) {
            $this->$current['Item_Attributes'] = [
                $element => $checkedArticleVersion
            ];
        } else {
            $this->addInvalid($context, 'Item_Attributes', [
                $element => $value
            ]);
        }
    }

    protected function parseItemIdentifier($position, $element, $value, $context)
    {
        $elementName = $this->getElementName($element, $context);
        $current = 'current' . $context;

        if (trim($value) !== $value) {
            $data = $this->formatData($elementName, $value);
            $this->checkResult->addError("{$elementName} value includes whitespace",
                "{$elementName} value '{$value}' includes whitespace", $position, $data);
            $value = trim($value);
        }

        if ($value === '') {
            return;
        }

        $itemIdentifiers = $this->config->getIdentifiers('Item', $this->getFormat());
        if (! isset($itemIdentifiers[$element])) {
            throw new \Exception("System Error - Config: unknown item identifier {$element}");
        }
        if (! isset($itemIdentifiers[$element]['check'])) {
            throw new \Exception("System Error - Config: check missing for item identifier {$element}");
        }

        $check = $itemIdentifiers[$element]['check'];
        $checkedValue = $this->$check($position, $elementName, $value);
        if ($element === 'Proprietary_ID') {
            $element = 'Proprietary';
        }
        if ($checkedValue !== false) {
            if (! isset($this->$current['Item_ID'])) {
                $this->$current['Item_ID'] = [];
            }
            if (isset($this->$current['Item_ID'][$element])) {
                throw new \Exception("System Error - parseitemIdentifier; duplicate identifier {$elementName}");
            }
            $this->$current['Item_ID'][$element] = $checkedValue;
        } else {
            $this->addInvalid($context, 'Item_ID', [
                $element => $value
            ]);
        }
    }

    protected function parseParentComponentTitle($position, $element, $value, $context)
    {
        $elementName = $this->getElementName($element, $context);
        $current = 'current' . $context;

        if (trim($value) !== $value) {
            $data = $this->formatData($elementName, $value);
            $this->checkResult->addWarning("{$elementName} value includes whitespace",
                "{$elementName} value '{$value}' includes whitespace", $position, $data);
            $value = trim($value);
        }

        if ($value === '') {
            // whether a value must be present will be checked later in checkRequiredParentComponentColumns
            return;
        }

        $this->$current['Item_Name'] = $value;
    }

    protected function parseParentComponentDataType($position, $element, $value, $context)
    {
        $elementName = $this->getElementName($element, $context);

        if (trim($value) !== $value) {
            $data = $this->formatData($elementName, $value);
            $this->checkResult->addError("{$elementName} value includes whitespace",
                "{$elementName} value '{$value}' includes whitespace", $position, $data);
            $value = trim($value);
        }

        if ($value === '') {
            // whether a value must be present will be checked later in checkRequiredParentComponentColumns
            return;
        }

        $this->parseEnumeratedElement($position, $element, $value, $context);
    }

    protected function parseReportingPeriodTotal($position, $element, $value, $context)
    {
        $current = 'current' . $context;

        $data = $this->formatData($element, $value);
        if (trim($value) !== $value) {
            $this->checkResult->addError("{$element} value includes whitespace",
                "{$element} value '{$value}' includes whitespace", $position, $data);
            $value = trim($value);
        }
        if ($value === '') {
            $message = "{$element} must not be empty";
            $this->checkResult->addCriticalError($message, $message, $position, $data);
            return;
        }
        if (! is_numeric($value)) {
            $this->checkResult->addCriticalError("{$element} value is invalid", "{$element} value is invalid", $position,
                $data, "value must be an integer");
            return;
        }
        $value = (int) $value;
        if ($value < 0) {
            $this->checkResult->addCriticalError("Negative {$element} value is invalid",
                "Negative {$element} value is invalid", $position, $data);
        }
        if ($value === 0) {
            $message = "{$element} value '0' is invalid";
            $this->checkResult->addCriticalError($message, $message, $position, $data,
                "rows with zero {$element} must be omitted");
        }

        $this->$current[$element] = $value;
    }

    protected function parseMonthlyData($position, $element, $value, $context)
    {
        $current = 'current' . $context;

        $data = $this->formatData($element . ' value', $value);
        if (trim($value) !== $value) {
            $this->checkResult->addError("{$element} value includes whitespace",
                "{$element} value '{$value}' includes whitespace", $position, $data);
            $value = trim($value);
        }
        if ($value === '') {
            $message = "{$element} value must not be empty";
            $this->checkResult->addError($message, $message, $position, $data,
                "set the cell value to 0 if there was no usage in {$element}");
            return;
        }
        if (! is_numeric($value)) {
            $this->checkResult->addCriticalError("{$element} value is invalid", "{$element} value is invalid", $position,
                $data, "value must be an integer");
            return;
        }
        $value = (int) $value;
        if ($value < 0) {
            $this->checkResult->addCriticalError("Negative {$element} value is invalid",
                "Negative {$element} value is invalid", $position, $data);
        }

        if (! isset($this->$current['Counts'])) {
            $this->$current['Counts'] = [];
        }
        // the current day is automatically added which might result in a wrong month, so we have to add a valid day
        $datetime = \DateTime::createFromFormat('d-M-Y', '01-' . $element);
        $this->$current['Counts'][$datetime->format('Y-m')] = $value;
    }

    protected function countsToPerformance()
    {
        if (! isset($this->currentItem['Counts'])) {
            return;
        }

        if (! isset($this->currentItem['Metric_Type'])) {
            // without Metric_Type the counts are useless
            unset($this->currentItem['Counts']);
            return;
        }
        $metricType = $this->currentItem['Metric_Type'];

        // remember the Metric_Types present in the report (used in checkMetrics)
        if (! isset($this->metricTypePresent[$metricType])) {
            $this->metricTypePresent[$metricType] = true;
        }

        $counts = [];
        foreach ($this->currentItem['Counts'] as $beginPeriod => $count) {
            if ($count > 0) {
                $counts[$beginPeriod] = $count;
            }
        }
        if (! empty($counts)) {
            $current = (empty($this->currentComponent) ? 'currentItem' : 'currentComponent');
            $this->$current['Performance'] = [
                $metricType => $counts
            ];
        }
        unset($this->currentItem['Counts']);
        unset($this->currentItem['Metric_Type']);
    }
}
