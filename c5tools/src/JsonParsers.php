<?php
namespace ubfr\c5tools;

trait JsonParsers
{

    protected function parse()
    {
        if (! is_object($this->json)) {
            $message = 'Report must be an object';
            $this->checkResult->fatalError($message, $message);
        }

        $properties = $this->getObjectProperties(null, null, $this->json, [
            'Report_Header',
            'Report_Items'
        ]);

        if (! isset($properties['Report_Header'])) {
            $message = 'Report_Header is missing';
            $this->checkResult->fatalError($message, $message);
        }
        $this->jsonHeaders = $properties['Report_Header'];
        $this->parseHeader($properties['Report_Header']);

        try {
            $this->computeElements();
            $this->computeValues();

            if (isset($properties['Report_Items'])) {
                $this->parseItems($properties['Report_Items']);
            }
            $this->checkMetrics();
        } catch (ParseException $e) {
            // ignore ParseException, error message is part of CheckResult
        }
    }

    protected function parseHeader(&$header)
    {
        if (! is_object($header)) {
            $message = 'Report_Header must be an object';
            $this->checkResult->fatalError($message, $message);
        }

        $headers = $this->config->getReportHeaders($this->getFormat());
        $requiredHeaders = [];
        $optionalHeaders = [];
        foreach ($headers as $headerName => $headerConfig) {
            if ($headerConfig['required']) {
                $requiredHeaders[] = $headerName;
            } else {
                $optionalHeaders[] = $headerName;
            }
        }
        $properties = $this->getObjectProperties('Report_Header', 'Report_Header', $header, $requiredHeaders,
            $optionalHeaders);
        if (! isset($properties['Release'])) {
            $message = 'Report_Header.Release is missing';
            $this->checkResult->fatalError($message, $message);
        }
        if (! isset($properties['Report_ID'])) {
            $message = 'Report_Header.Report_ID is missing';
            $this->checkResult->fatalError($message, $message);
        }
        foreach ($headers as $headerName => $headerConfig) {
            if (! isset($properties[$headerName])) {
                continue;
            }
            if (isset($headerConfig['parse'])) {
                $parse = $headerConfig['parse'];
                $this->$parse("Report_Header.{$headerName}", $properties[$headerName]);
            } else {
                $check = $headerConfig['check'];
                $result = $this->$check("Report_Header.{$headerName}", $headerName, $properties[$headerName]);
                if ($result !== false) {
                    $this->headers[$headerName] = $result;
                }
            }
        }
    }

    protected function parseExceptions($position, $exceptions)
    {
        if (! $this->isNonEmptyArray($position, 'Exceptions', $exceptions)) {
            return;
        }

        $checkedExceptions = [];
        foreach ($exceptions as $index => $exception) {
            $properties = $this->getObjectProperties("{$position}[$index]", 'Exception', $exception,
                [
                    'Code',
                    'Severity',
                    'Message'
                ], [
                    'Help_URL',
                    'Data'
                ]);
            if (isset($properties['Invalid']) && count($properties) === 1) {
                continue;
            }
            $checkedException = $this->checkedException($position, 'Exception', $properties);
            if ($checkedException !== false) {
                $checkedExceptions[] = $checkedException;
            }
        }

        if (! empty($checkedExceptions)) {
            $this->headers['Exceptions'] = $checkedExceptions;
        }
    }

    protected function parseReportAttributes($position, $attributes)
    {
        if (! $this->isMasterReport()) {
            $message = 'Report_Attributes is not permitted for Standard Views';
            $this->checkResult->addError($message, $message, $position, 'Report_Attributes');
            return;
        }

        if (! $this->isNonEmptyArray($position, 'Report_Attributes', $attributes)) {
            return;
        }

        $permittedAttributes = array_keys($this->config->getReportAttributes($this->getReportId(), $this->getFormat()));
        $attributes = $this->getKeyValues($position, 'Report_Attributes', $attributes, 'Name', 'Value',
            $permittedAttributes);
        if (isset($attributes['Invalid'])) {
            // ignore invalid attributes
            unset($attributes['Invalid']);
        }
        parent::parseReportAttributes($position, $attributes);
    }

    protected function parseReportFilters($position, $filters)
    {
        $permittedFilters = array_keys($this->config->getReportFilters($this->getReportId()));
        $filters = $this->getKeyValues($position, 'Report_Filters', $filters, 'Name', 'Value', $permittedFilters);
        if (isset($filters['Invalid'])) {
            // ignore invalid filters
            unset($filters['Invalid']);
        }
        parent::parseReportFilters($position, $filters);
    }

    protected function parseItems(&$items)
    {
        if (! is_array($items)) {
            $message = 'Report_Items must e an array';
            $this->checkResult->fatalError($message, $message);
        }

        foreach ($items as $index => $item) {
            $this->parseItem($index, $item);
        }
    }

    protected function parseItem($index, &$item)
    {
        static $requiredElements = null;
        static $optionalElements = null;

        if ($requiredElements === null) {
            $requiredElements = [];
            $optionalElements = [];
            foreach ($this->elements as $elementName => $elementConfig) {
                if ($elementConfig['required']) {
                    $requiredElements[] = $elementName;
                } else {
                    $optionalElements[] = $elementName;
                }
            }
        }

        $this->currentItem = [];
        $this->currentParent = [];
        $this->currentComponent = [];
        $this->currentComponents = [];
        $this->currentSectionTypePosition = null;

        $position = "Report_Items[{$index}]";
        $properties = $this->getObjectProperties($position, 'Report_Items', $item, $requiredElements, $optionalElements);
        if (isset($properties['Invalid'])) {
            $this->currentItem['Invalid'] = $properties['Invalid'];
            unset($properties['Invalid']);
        }
        foreach ($this->elements as $elementName => $elementConfig) {
            if (isset($properties[$elementName])) {
                $parse = $elementConfig['parse'];
                $this->$parse("{$position}.{$elementName}", $elementName, $properties[$elementName], 'Item');
            }
        }

        if (! empty($this->currentComponents)) {
            $this->checkItemMetricTypes($position);
            $this->currentItem['Item_Component'] = $this->currentComponents;
        }

        // checks which require correlation between different elements of the Report_Items
        $this->checkItemTitleIdentifiers($position);
        if (isset($this->elements['Section_Type'])) {
            $this->checkSectionType($position);
        }

        $this->storeCurrentParent($position);
        $this->storeCurrentItem($position);

        $this->debugItem($index);
    }

    protected function parseItemIdentifiers($position, $element, $identifiers, $context)
    {
        $current = 'current' . $context;

        if (! $this->isNonEmptyArray($position, $element, $identifiers)) {
            $this->addInvalid($context, $element, [
                'not_non_empty_array' => $identifiers
            ]);
            return;
        }

        $permittedIdentifiers = $this->config->getIdentifiers('Item', $this->getFormat());
        $checkedIdentifiers = $this->checkedIdentifiers($position, $element, $identifiers, $permittedIdentifiers);
        if (isset($checkedIdentifiers['Invalid'])) {
            $this->addInvalid($context, $element, $checkedIdentifiers['Invalid']);
            unset($checkedIdentifiers['Invalid']);
        }
        if (! empty($checkedIdentifiers)) {
            $this->$current['Item_ID'] = $checkedIdentifiers;
        }
    }

    protected function parseItemName($position, $element, $itemName, $context)
    {
        $elementName = $this->getElementName($element, $context);
        $current = 'current' . $context;

        if (($value = $this->checkedIsString($position, $element, $itemName)) === false) {
            $this->addInvalid($context, $element, $itemName);
            return;
        }

        if (trim($value) !== $value) {
            $data = $this->formatData($elementName, $value);
            $this->checkResult->addWarning("{$elementName} value includes whitespace",
                "{$elementName} value '{$value}' includes whitespace", $position, $data);
            $value = trim($value);
        }

        $this->$current[$element] = $value;
    }

    protected function parseItemContributors($position, $element, $contributors, $context)
    {
        $current = 'current' . $context;

        if (! $this->isNonEmptyArray($position, $element, $contributors)) {
            $this->addInvalid($context, $element, [
                'not_non_empty_array' => $contributors
            ]);
            return;
        }

        foreach ($contributors as $index => $contributor) {
            $positionIndex = "{$position}[{$index}]";
            if (! is_object($contributor)) {
                $message = "{$element}[$index] must be an object";
                $this->checkResult->addCriticalError($message, $message, $positionIndex, "{$element}[{$index}]");
                $this->addInvalid($context, $element, [
                    $index => [
                        'not_an_object' => $contributor
                    ]
                ]);
                continue;
            }
            $properties = $this->getObjectProperties($positionIndex, $element, $contributor, [
                'Type',
                'Name'
            ], [
                'Identifier'
            ]);
            if (! isset($properties['Type']) || ! isset($properties['Name'])) {
                $this->addInvalid($context, $element, [
                    $index => $contributor
                ]);
                continue;
            }
            if (isset($properties['Invalid'])) {
                $this->addInvalid($context, $element, [
                    $index => $properties['Invalid']
                ]);
                unset($properties['Invalid']);
            }
            if (isset($properties['Identifier'])) {
                $checkedIdentifier = $this->checkedAuthorIdentifier("{$positionIndex}.Identifier", 'Identifier',
                    $properties['Identifier'], $properties['Identifier']);
                if ($checkedIdentifier !== false) {
                    $properties['Identifier'] = $checkedIdentifier;
                } else {
                    $this->addInvalid($context, $element, [
                        $index => [
                            'Identifier' => $properties['Identifier']
                        ]
                    ]);
                    unset($properties['Identifier']);
                }
            }
            if (! isset($this->$current[$element])) {
                $this->$current[$element] = [];
            }
            $this->$current[$element][] = $properties;
        }
    }

    protected function parseItemDates($position, $element, $dates, $context)
    {
        $current = 'current' . $context;

        if (! $this->isNonEmptyArray($position, $element, $dates)) {
            $this->addInvalid($context, $element, [
                'not_non_empty_array' => $dates
            ]);
            return;
        }

        $permittedTypes = [
            'Publication_Date'
        ];
        $checkedDates = $this->getKeyValues($position, $element, $dates, 'Type', 'Value', $permittedTypes);
        if (isset($checkedDates['Invalid'])) {
            $invalid = $checkedDates['Invalid'];
            unset($checkedDates['Invalid']);
        } else {
            $invalid = [];
        }

        $itemDates = [];
        foreach ($checkedDates as $type => $valuePositions) {
            foreach ($valuePositions as $index => $valuePosition) {
                $date = $this->checkedDate("{$valuePosition['p']}.Value", $type, $valuePosition['v']);
                if ($index > 0) {
                    $message = "{$type} specified multiple times";
                    $data = $this->formatdata($type, $valuePosition['v']);
                    $this->checkResult->addError($message, $message, $valuePosition['p'], $data,
                        'ignoring all but the first occurrence');
                    $invalid[] = [
                        $type => ($date ?? $valuePosition['v'])
                    ];
                    continue;
                }
                if ($date !== false) {
                    $itemDates[$type] = $date;
                } else {
                    $invalid[] = [
                        $type => $valuePosition['v']
                    ];
                    continue;
                }
            }
        }

        if (! empty($itemDates)) {
            $this->$current['Item_Dates'] = $itemDates;
        }
        if (! empty($invalid)) {
            $this->addInvalid($context, $element, $invalid);
        }
    }

    protected function parseItemAttributes($position, $element, $attributes, $context)
    {
        $current = 'current' . $context;

        if (! $this->isNonEmptyArray($position, $element, $attributes)) {
            $this->addInvalid($context, $element, [
                'not_non_empty_array' => $attributes
            ]);
            return;
        }

        $permittedTypes = [
            'Article_Version' => 'checkedArticleVersion',
            'Article_Type' => 'checkedArticleType',
            'Qualification_Name' => 'checkedQualificationName',
            'Qualification_Level' => 'checkedQualificationLevel',
            'Proprietary' => 'checkedProprietaryAttribute'
        ];
        $checkedAttributes = $this->getKeyValues($position, $element, $attributes, 'Type', 'Value',
            array_keys($permittedTypes));
        if (isset($checkedAttributes['Invalid'])) {
            $invalid = $checkedAttributes['Invalid'];
            unset($checkedAttributes['Invalid']);
        } else {
            $invalid = [];
        }

        $itemAttributes = [];
        foreach ($checkedAttributes as $type => $valuePositions) {
            foreach ($valuePositions as $index => $valuePosition) {
                $check = $permittedTypes[$type];
                $checkedValue = $this->$check("{$valuePosition['p']}.Value", $type, $valuePosition['v'], $context);
                if ($index > 0) {
                    $message = "{$type} specified multiple times";
                    $data = $this->formatdata($type, $valuePosition['v']);
                    $this->checkResult->addError($message, $message, $valuePosition['p'], $data,
                        'ignoring all but the first occurrence');
                    $invalid[] = [
                        $type => ($checkedValue ?? $valuePosition['v'])
                    ];
                    continue;
                }
                if ($checkedValue !== false) {
                    $itemAttributes[$type] = $checkedValue;
                } else {
                    $invalid[] = [
                        $type => $valuePosition['v']
                    ];
                    continue;
                }
            }
        }

        if (! empty($itemAttributes)) {
            $this->$current['Item_Attributes'] = $itemAttributes;
        }
        if (! empty($invalid)) {
            $this->addInvalid($context, $element, $invalid);
        }
    }

    protected function parseItemParent($position, $element, &$itemParent, $context)
    {
        if (! isset($this->elements[$element]['elements'])) {
            throw new \Exception("System Error - Config: {$element} elements missing");
        }

        static $requiredElements = null;
        static $optionalElements = null;

        if ($requiredElements === null) {
            $requiredElements = [];
            $optionalElements = [];
            foreach ($this->elements[$element]['elements'] as $elementName => $elementConfig) {
                if ($elementConfig['required']) {
                    $requiredElements[] = $elementName;
                } else {
                    $optionalElements[] = $elementName;
                }
            }
        }

        if (! is_object($itemParent)) {
            $message = "{$element} must be an object";
            $this->checkResult->addCriticalError($message, $message, $position, $element);
            $this->addInvalid($context, $element, [
                'not_an_object' => $itemParent
            ]);
            return;
        }

        $properties = $this->getObjectProperties($position, $element, $itemParent, $requiredElements, $optionalElements);
        if (isset($properties['Invalid'])) {
            $this->currentParent['Invalid'] = $properties['Invalid'];
            unset($properties['Invalid']);
        }
        foreach ($this->elements[$element]['elements'] as $elementName => $elementConfig) {
            if (isset($properties[$elementName])) {
                $parse = $elementConfig['parse'];
                $this->$parse("{$position}.{$elementName}", $elementName, $properties[$elementName], 'Parent');
            }
        }
        // TODO: checks which require correlation between different elements within the Item
    }

    protected function parseItemComponent($position, $element, &$itemComponents, $context)
    {
        if (! isset($this->elements[$element]['elements'])) {
            throw new \Exception("System Error - Config: {$element} elements missing");
        }

        static $requiredElements = null;
        static $optionalElements = null;

        if ($requiredElements === null) {
            $requiredElements = [];
            $optionalElements = [];
            foreach ($this->elements[$element]['elements'] as $elementName => $elementConfig) {
                if ($elementConfig['required']) {
                    $requiredElements[] = $elementName;
                } else {
                    $optionalElements[] = $elementName;
                }
            }
        }

        if (! is_array($itemComponents)) {
            $message = "{$element} must be an array";
            $this->checkResult->addCriticalError($message, $message, $position, $element);
            $this->addInvalid($context, $element, [
                'not_an_array' => $itemComponents
            ]);
            return;
        }
        if (empty($itemComponents)) {
            $message = "{$element} must not be empty";
            $this->checkResult->addError($message, $message, $position, $element,
                'optional elements without a value must be omitted');
            return;
        }

        foreach ($itemComponents as $index => $itemComponent) {
            $this->currentComponent = [];
            $positionIndex = "{$position}[{$index}]";

            if (! is_object($itemComponent)) {
                $message = "{$positionIndex} must be an object";
                $this->checkResult->addCriticalError($message, $message, $positionIndex, $positionIndex);
                $this->addInvalid($context, $element, [
                    $index => [
                        'not_an_object' => $itemComponent
                    ]
                ]);
                continue;
            }

            $properties = $this->getObjectProperties($positionIndex, $positionIndex, $itemComponent, $requiredElements,
                $optionalElements);
            if (isset($properties['Invalid'])) {
                $this->addInvalid($context, $element, [
                    $index => $properties['Invalid']
                ]);
                unset($properties['Invalid']);
            }
            foreach ($this->elements[$element]['elements'] as $elementName => $elementConfig) {
                if (isset($properties[$elementName])) {
                    $parse = $elementConfig['parse'];
                    $this->$parse("{$positionIndex}.{$elementName}", $elementName, $properties[$elementName],
                        'Component');
                }
            }

            // TODO: checks which require correlation between different elements within the Component

            $this->storeCurrentComponent($positionIndex);
        }
    }

    protected function storeCurrentComponent($position)
    {
        if ($this->getReportId() !== 'IR' || ! $this->includesComponentDetails()) {
            return;
        }

        $this->checkComponentMetricTypes($position);

        $hash = $this->computeHash($this->currentComponent);
        if (! isset($this->currentComponents[$hash])) {
            $this->currentComponents[$hash] = $this->currentComponent;
            $this->currentComponents[$hash]['Positions'] = [
                $position
            ];
        } else {
            $message = 'Multiple Item_Components for the same Item, Report Attributes and Component';
            $data = "Item '" . ($this->currentItem['Item'] ?? '') . "', Component '" .
                ($this->currentComponent['Item_Name'] ?? '') . "' (other occurrence(s): " .
                implode(', ', $this->currentComponents[$hash]['Positions']) . ')';
            $hint = 'note that this will become an error one year after the publication of release 5.0.2';
            $this->checkResult->addNotice($message, $message, $position, $data, $hint);

            // merge Performance into existing Component
            foreach ($this->currentComponent['Performance'] as $metricType => $dateCounts) {
                if (! isset($this->currentComponents[$hash]['Performance'][$metricType])) {
                    $this->currentComponents[$hash]['Performance'][$metricType] = $dateCounts;
                } else {
                    $message = 'Multiple Item_Components for the same Item, Report Attributes, Component and Metric_Type';
                    if ($dateCounts != $this->currentComponents[$hash]['Performance'][$metricType]) {
                        $message .= ' with different Counts';
                    } else {
                        $message .= ' with identical Counts';
                    }
                    $message .= ', ignoring all but the first Count';
                    $data = "Item '" . ($this->currentItem['Item'] ?? '') . "', Component '" .
                        ($this->currentComponent['Item_Name'] ?? '') .
                        "', Metric_Type '{$metricType}' (other occurrence(s): " .
                        implode(', ', $this->currentComponents[$hash]['Positions']) . ')';
                    $this->checkResult->addCriticalError($message, $message, $position, $data);

                    $this->addInvalidComponent($hash, 'Performance', [
                        $metricType => $dateCounts
                    ]);
                }
            }

            // merge Invalid into existing Component
            if (isset($this->currentComponent['Invalid'])) {
                foreach ($this->currentComponent['Invalid'] as $key => $value) {
                    $this->addInvalidComponent($hash, $key, $value);
                }
            }

            $this->currentComponents[$hash]['Positions'][] = $position;
        }
    }

    protected function parsePerformance($position, $element, $performance, $context)
    {
        if (! $this->isNonEmptyArray($position, $element, $performance, false)) {
            $this->addInvalid($context, 'Performance', [
                'not_non_empty_array' => $performance
            ]);
            return;
        }

        $requiredElements = [
            'Period',
            'Instance'
        ];
        $this->currentDates = [];
        foreach ($performance as $index => $periodInstance) {
            $this->currentDate = false;
            $positionIndex = "{$position}[{$index}]";
            $properties = $this->getObjectProperties($positionIndex, $element, $periodInstance, $requiredElements);
            if (isset($properties['Invalid'])) {
                $this->addInvalid($context, 'Performance', $properties['Invalid']);
                unset($properties['Invalid']);
            }
            if (isset($properties['Period'])) {
                $this->parsePeriod("{$positionIndex}.Period", 'Period', $properties['Period'], $context);
            }
            if (isset($properties['Instance'])) {
                $this->parseInstance("{$positionIndex}.Instance", 'Instance', $properties['Instance'], $context);
            }
        }
    }

    protected function parsePeriod($position, $element, $period, $context)
    {
        $requiredElements = [
            'Begin_Date',
            'End_Date'
        ];
        $properties = $this->getObjectProperties($position, $element, $period, $requiredElements);
        if (isset($properties['Invalid'])) {
            $this->addInvalid($context, 'Performance', [
                'Period' => $properties['Invalid']
            ]);
            unset($properties['Invalid']);
        }
        if (isset($properties['Begin_Date'])) {
            $beginDate = $this->checkedDate("{$position}.Begin_Date", 'Begin_Date', $properties['Begin_Date']);
        } else {
            $beginDate = false;
        }
        if (isset($properties['End_Date'])) {
            $endDate = $this->checkedDate("{$position}.End_Date", 'End_Date', $properties['End_Date']);
        } else {
            $endDate = false;
        }
        if ($beginDate === false || $endDate === false) {
            return;
        }

        switch ($this->getGranularity()) {
            case self::GRANULARITY_MONTH:
                if ($beginDate < $this->beginDate || $this->endDate < $beginDate) {
                    $data = $this->formatData('Begin_Date', $beginDate);
                    $this->checkResult->addCriticalError("Begin_Date value is outside the reporting period",
                        "Begin_Date value '{$beginDate}' is outside the reporting period ('{$this->beginDate}' to '{$this->endDate}')",
                        "{$position}.Begin_Date", $data);
                    $beginDate = false;
                }
                if ($endDate < $this->beginDate || $this->endDate < $endDate) {
                    $data = $this->formatData('End_Date', $endDate);
                    $this->checkResult->addCriticalError("End_Date value is outside the reporting period",
                        "End_Date value '{$endDate}' is outside the reporting period ('{$this->beginDate}' to '{$this->endDate}')",
                        "{$position}.End_Date", $data);
                    $endDate = false;
                }
                if ($beginDate !== false && $endDate !== false) {
                    if ($beginDate > $endDate) {
                        $data = $this->formatData('Period', "{$beginDate}' to '{$endDate}");
                        $this->checkResult->addCriticalError("Period is invalid, End_Date is before Begin_Date",
                            "Period '{$beginDate}' to '{$endDate}' is invalid, End_Date is before Begin_Date", $position,
                            $data);
                        $beginDate = false;
                        $endDate = false;
                    } else {
                        $dt = \DateTime::createFromFormat('Y-m-d', $beginDate);
                        $expectedEndDate = $dt->format('Y-m-t');
                        if ($endDate !== $expectedEndDate) {
                            $data = $this->formatData('Period', "{$beginDate}' to '{$endDate}");
                            $this->checkResult->addCriticalError("Period is invalid",
                                "Period '{$beginDate}' to '{$endDate}' is invalid", $position, $data,
                                'for Granularity Month the Performance.Period must be exactly one month');
                            $beginDate = false;
                            $endDate = false;
                        }
                    }
                }
                break;
            case self::GRANULARITY_TOTALS:
                if ($beginDate !== $this->beginDate) {
                    $data = $this->formatData('Begin_Date', $beginDate);
                    $this->checkResult->addCriticalError("Begin_Date value is invalid",
                        "Begin_Date value '{$beginDate}' is invalid (expected '{$this->beginDate}')",
                        "{$position}.Begin_Date", $data,
                        'for Granularity Totals the Performance.Period.Begin_Date must be identical with the reporting period Begin_Date');
                    $beginDate = false;
                }
                if ($endDate !== $this->endDate) {
                    $data = $this->formatData('End_Date', $endDate);
                    $this->checkResult->addCriticalError("End_Date value is invalid",
                        "End_Date value '{$endDate}' is invalid (expected '{$this->endDate}')", "{$position}.End_Date",
                        $data,
                        "for Granularity Totals the Performance.Period.End_Date must be identical with the reporting period End_Date");
                    $endDate = false;
                }
                break;
            default:
                throw new \Exception(
                    "System Error - parsePeriod: unknown granularity '" . $this->getGranularity() . "'");
                break;
        }
        if ($beginDate !== false && $endDate !== false) {
            $dt = \DateTime::createFromFormat('Y-m-d', $beginDate);
            $this->currentDate = $dt->format('Y-m');

            if (! isset($this->currentDates[$this->currentDate])) {
                $this->currentDates[$this->currentDate] = $position;
            } else {
                $data = $this->formatData('Period', "{$beginDate}' to '{$endDate}");
                $summary = 'Multiple Instances for the same Period';
                $message = $summary . ', first occurrence at ' . $this->currentDates[$this->currentDate];
                $hint = 'note that this will become an error one year after the publication of release 5.0.2';
                $this->checkResult->addNotice($summary, $message, $position, $data, $hint);
            }
        }
    }

    protected function parseInstance($position, $element, $instance, $context)
    {
        $current = 'current' . $context;

        if (! $this->isNonEmptyArray($position, $element, $instance)) {
            $this->addInvalid($context, 'Performance', [
                'Instance' => [
                    'not_non_empty_array' => $instance
                ]
            ]);
            return;
        }

        $requiredElements = [
            'Metric_Type',
            'Count'
        ];
        foreach ($instance as $index => $metricTypeCount) {
            $positionIndex = "{$position}[{$index}]";
            $properties = $this->getObjectProperties($positionIndex, $element, $metricTypeCount, $requiredElements);
            if (isset($properties['Invalid'])) {
                $this->addInvalid($context, 'Performance', [
                    'Instance' => $properties['Invalid']
                ]);
                unset($properties['Invalid']);
            }
            $metricType = false;
            if (isset($properties['Metric_Type'])) {
                $positionMetricType = "{$positionIndex}.Metric_Type";
                $this->parseEnumeratedElement($positionMetricType, 'Metric_Type', $properties['Metric_Type'], $context);
                if (isset($this->$current['Metric_Type'])) {
                    $metricType = $this->$current['Metric_Type'];
                    unset($this->$current['Metric_Type']);

                    // remember the Metric_Types present in the report (used in checkMetrics)
                    if (! isset($this->metricTypePresent[$metricType])) {
                        $this->metricTypePresent[$metricType] = true;
                    }
                }
            }
            if (! isset($properties['Count'])) {
                continue;
            }
            $positionCount = "{$positionIndex}.Count";
            if (! is_scalar($properties['Count'])) {
                $message = "Count must be an integer";
                $this->checkResult->addCriticalError($message, $message, $positionCount, 'Count');
                continue;
            }
            $count = $properties['Count'];
            $data = $this->formatData('Count', $count);
            if (trim($count) === '') {
                $message = "Count must not be empty";
                $this->checkResult->addError($message, $message, $positionCount, $data,
                    'if there was no usage in the period the Instance must be omitted');
                continue;
            }
            if (! is_numeric($count)) {
                $message = "Count must be an integer";
                $this->checkResult->addCriticalError($message, $message, $positionCount, $data);
                continue;
            }
            if (! is_int($count)) {
                $message = "Count must be an integer";
                $this->checkResult->addError($message, $message, $positionCount, $data);
                $count = (int) $count;
            }
            if ($count < 0) {
                $message = 'Negative Count value is invalid';
                $this->checkResult->addCriticalError($message, $message, $positionCount, $data);
                continue;
            }
            if ($count === 0) {
                $message = "Count must not be zero";
                $this->checkResult->addError($message, $message, $positionCount, $data,
                    'if there was no usage in the period the Instance must be omitted');
                continue;
            }
            if ($metricType !== false && $this->currentDate !== false) {
                if (! isset($this->$current['Performance'])) {
                    $this->$current['Performance'] = [];
                }
                if (! isset($this->$current['Performance'][$metricType])) {
                    $this->$current['Performance'][$metricType] = [];
                }
                if (isset($this->$current['Performance'][$metricType][$this->currentDate])) {
                    $existingCount = $this->$current['Performance'][$metricType][$this->currentDate];
                    $data = "Begin_Date {$this->currentDate}-01, Metric_Type {$metricType}, Count {$count}";
                    $itemNameElement = $this->getItemNameElement($context);
                    if (isset($this->$current[$itemNameElement])) {
                        $data = $itemNameElement . ' ' . $this->$current[$itemNameElement] . ', ' . $data;
                    }
                    $message = 'Multiple Instances for the same Period and Metric_Type';
                    if ($count !== $existingCount) {
                        $data .= " (existing Count {$existingCount})";
                        $message .= ' with different Counts';
                    } else {
                        $message .= ' with identical Counts';
                    }
                    $message .= ', ignoring all but the first Instance';
                    $data = $this->formatData('Instance', $data);
                    $this->checkResult->addCriticalError($message, $message, $positionIndex, $data);
                } else {
                    $this->$current['Performance'][$metricType][$this->currentDate] = $count;
                }
            }
            // TODO: add count to Invalid (requires change to Invalid handling in parseEnumeratedElements)
        }
    }
}
