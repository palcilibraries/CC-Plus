<?php
namespace ubfr\c5tools;

trait Parsers
{

    protected function addInvalid($context, $element, $value)
    {
        $current = 'current' . $context;
        if (! isset($this->$current['Invalid'])) {
            $this->$current['Invalid'] = [];
        }
        if (! isset($this->$current['Invalid'][$element])) {
            $this->$current['Invalid'][$element] = $value;
        } else {
            if (! is_array($this->$current['Invalid'][$element])) {
                $this->$current['Invalid'][$element] = [
                    $this->$current['Invalid'][$element]
                ];
            }
            if (is_array($value)) {
                $this->$current['Invalid'][$element] = array_merge_recursive($this->$current['Invalid'][$element],
                    $value);
            } else {
                $this->$current['Invalid'][$element][] = $value;
            }
        }
    }

    protected function addInvalidComponent($hash, $element, $value)
    {
        if (! isset($this->currentComponents[$hash]['Invalid'])) {
            $this->currentComponents[$hash]['Invalid'] = [];
        }
        if (! isset($this->currentComponents[$hash]['Invalid'][$element])) {
            $this->currentComponents[$hash]['Invalid'][$element] = $value;
        } else {
            if (! is_array($this->currentComponents[$hash]['Invalid'][$element])) {
                $this->currentComponents[$hash]['Invalid'][$element] = [
                    $this->currentComponents[$hash]['Invalid'][$element]
                ];
            }
            if (is_array($value)) {
                $this->currentComponents[$hash]['Invalid'][$element] = array_merge_recursive(
                    $this->currentComponents[$hash]['Invalid'][$element], $value);
            } else {
                $this->currentComponents[$hash]['Invalid'][$element][] = $value;
            }
        }
    }

    protected function copyItemToComponent()
    {
        $component = [];
        if (isset($this->currentItem['Item'])) {
            $component['Item_Name'] = $this->currentItem['Item'];
        }
        foreach ([
            'Item_ID',
            'Item_Contributors',
            'Item_Dates',
            'Item_Attributes',
            'Data_Type'
        ] as $element) {
            if (isset($this->currentItem[$element])) {
                $component[$element] = $this->currentItem[$element];
            }
        }
        return $component;
    }

    protected function storeCurrentParent($position)
    {
        if (! $this->includesParentData()) {
            return;
        }

        $itemHash = $this->computeHash($this->currentItem);
        if (empty($this->currentParent)) {
            if (isset($this->items[$itemHash]) && isset($this->items[$itemHash]['Item_Parent'])) {
                $message = 'Inconsistent Parents for Item, Item previously occured with a Parent, ignoring all but the first occurrence';
                $this->checkResult->addCriticalError($message, $message, $position, 'Parent');
                $this->addInvalid('Item', 'Item_Parent', 'none');
            }
        } else {
            $hash = $this->computeHash($this->currentParent);
            if (isset($this->items[$itemHash])) {
                if (! isset($this->items[$itemHash]['Item_Parent'])) {
                    $message = 'Inconsistent Parents for Item, Item previously occured without a Parent, ignoring all but the first occurrence';
                    $this->checkResult->addCriticalError($message, $message, $position, 'Parent');
                    $this->addInvalid('Item', 'Item_Parent', $this->currentParent);
                    return;
                }
                if ($this->computeHash($this->items[$itemHash]['Item_Parent']) !== $hash) {
                    $message = 'Inconsistent Parents for Item, Item previously occured with a differnt Parent, ignoring all but the first occurrence';
                    $this->checkResult->addCriticalError($message, $message, $position, 'Parent');
                    $this->addInvalid('Item', 'Item_Parent', $this->currentParent);
                    return;
                }
            }
            $this->currentItem['Item_Parent'] = $this->currentParent;
        }
    }

    protected function storeCurrentItem($index)
    {
        $hash = $this->computeHash($this->currentItem);
        if (! isset($this->items[$hash])) {
            $this->items[$hash] = $this->currentItem;
            $this->items[$hash]['Positions'] = [
                $index
            ];
        } else {
            if ($this->getFormat() === self::FORMAT_JSON) {
                $message = 'Multiple Report_Items for the same Item and Report Attributes';
                $itemNameElement = $this->getItemNameElement('Item');
                $data = $itemNameElement . " '" . ($this->items[$hash][$itemNameElement] ?? '') .
                    "' (other occurrence(s): " . implode(', ', $this->items[$hash]['Positions']) . ')';
                $hint = 'note that this will become an error one year after the publication of release 5.0.2';
                $this->checkResult->addNotice($message, $message, "Report_Items[{$index}]", $data, $hint);
            }
            if (isset($this->currentItem['Performance'])) {
                if (! isset($this->items[$hash]['Performance'])) {
                    $this->items[$hash]['Performance'] = $this->currentItem['Performance'];
                } else {
                    $this->mergeItemPerformance($index, $hash);
                }
            }
            if (isset($this->currentItem['Item_Component'])) {
                if (! isset($this->items[$hash]['Item_Component'])) {
                    $this->items[$hash]['Item_Component'] = $this->currentItem['Item_Component'];
                } else {
                    foreach ($this->currentItem['Item_Component'] as $componentHash => $component) {
                        if (! isset($this->items[$hash]['Item_Component'][$componentHash])) {
                            $this->items[$hash]['Item_Component'][$componentHash] = $component;
                        } else {
                            if ($this->getFormat() === self::FORMAT_JSON) {
                                $message = 'Multiple Item_Components for the same Item, Report Attributes and Component';
                                $itemNameElement = $this->getItemNameElement('Item');
                                $data = $itemNameElement . " '" . ($this->items[$hash][$itemNameElement] ?? '') .
                                    "', Component '" . ($this->items[$hash]['Item_Component']['Item_Name'] ?? '') .
                                    "' (other occurrence(s): " .
                                    implode(', ', $this->items[$hash]['Item_Component'][$componentHash]['Positions']) .
                                    ')';
                                ;
                                $hint = 'note that this will become an error one year after the publication of release 5.0.2';
                                $this->checkResult->addNotice($message, $message, "Report_Items[{$index}]", $data, $hint);
                            }
                            $this->mergeComponentPerformance($index, $hash, $componentHash);
                        }
                    }
                    $this->items[$hash]['Item_Component'][$componentHash]['Positions'] = array_merge(
                        $this->items[$hash]['Item_Component'][$componentHash]['Positions'], $component['Positions']);
                }
            }
            $this->items[$hash]['Positions'][] = $index;
        }
    }

    protected function mergeItemPerformance($index, $hash)
    {
        $occurrence = ($this->getFormat() === self::FORMAT_JSON ? 'Report_Item' : 'row');
        foreach ($this->currentItem['Performance'] as $metricType => $dateCounts) {
            if (! isset($this->items[$hash]['Performance'][$metricType])) {
                $this->items[$hash]['Performance'][$metricType] = $dateCounts;
            } else {
                $message = "Multiple {$occurrence}s for the same Item, Report Attributes and Metric_Type";
                if ($dateCounts != $this->items[$hash]['Performance'][$metricType]) {
                    $message .= ' with different Counts';
                } else {
                    $message .= ' with identical Counts';
                }
                $message .= ", ignoring all but the first {$occurrence}";
                $position = ($this->getFormat() === self::FORMAT_JSON ? "Report_Items[$index}" : $index);
                $itemNameElement = $this->getItemNameElement('Item');
                $data = $itemNameElement . " '" . ($this->items[$hash][$itemNameElement] ?? '') .
                    "', Metric_Type '{$metricType}' (other {$occurrence}(s): " .
                    implode(', ', $this->items[$hash]['Positions']) . ')';
                $this->checkResult->addCriticalError($message, $message, $position, $data);
            }
        }
    }

    protected function mergeComponentPerformance($index, $hash, $componentHash)
    {
        $occurrence = ($this->getFormat() === self::FORMAT_JSON ? 'Report_Item' : 'row');
        foreach ($this->currentItem['Item_Component'][$componentHash]['Performance'] as $metricType => $dateCounts) {
            if (! isset($this->items[$hash]['Item_Component'][$componentHash]['Performance'][$metricType])) {
                $this->items[$hash]['Item_Component'][$componentHash]['Performance'][$metricType] = $dateCounts;
            } else {
                $message = "Multiple {$occurrence}s for the same Item, Component and Metric_Type";
                if ($dateCounts != $this->items[$hash]['Item_Component'][$componentHash]['Performance'][$metricType]) {
                    $message .= ' with different Counts';
                } else {
                    $message .= ' with identical Counts';
                }
                $message .= ", ignoring all but the first {$occurrence}";
                $position = ($this->getFormat() === self::FORMAT_JSON ? "Report_Items[$index}" : $index);
                $data = "Item '" . ($this->items[$hash]['Item'] ?? '') . "', Component '" .
                    ($this->items[$hash]['Item_Component'][$componentHash]['Item_Name'] ?? '') .
                    ", Metric_Type '{$metricType}' (other {$occurrence}(s): " .
                    implode(', ', $this->items[$hash]['Positions']) . ')';
                $this->checkResult->addCriticalError($message, $message, $position, $data);
            }
        }
    }

    protected function parseReportId($position, $id)
    {
        if (($id = $this->checkedIsString($position, 'Report_ID', $id)) === false) {
            return;
        }

        $reportIds = $this->config->getReportIds();
        if (in_array($id, $reportIds)) {
            $this->headers['Report_ID'] = $id;
            return;
        }
        $correctId = $this->inArrayLax($id, $reportIds);
        if ($correctId !== false) {
            $data = $this->formatData('Report_ID', $id);
            $this->checkResult->addCriticalError('Spelling of Report_ID is wrong',
                "Spelling of Report_ID '{$id}' is wrong", $position, $data, "must be spelled '{$correctId}'");
            $this->headers['Report_ID'] = $correctId;
            return;
        }
        if (preg_match('/^[a-zA-Z0-9]+:/', $id)) {
            $this->checkResult->fatalError('Custom report is not support',
                "Custom report with Report_ID '{$id}' is not supported");
        } else {
            $this->checkResult->fatalError('Report_ID is invalid', "Report_ID '{$id}' is invalid");
        }
    }

    protected function parseReportName($position, $name)
    {
        if (($name = $this->checkedIsString($position, 'Report_Name', $name)) === false) {
            return;
        }

        $reportId = $this->getReportId();
        $reportNameForId = $this->config->getReportName($reportId);
        if ($name !== $reportNameForId) {
            $data = $this->formatData('Report_Name', $name);
            if ($this->lax($name) == $this->lax($reportNameForId)) {
                $this->checkResult->addCriticalError('Spelling of Report_Name is wrong',
                    "Spelling of Report_Name '{$name}' is wrong", $position, $data,
                    "must be spelled '{$reportNameForId}'");
            } else {
                $this->checkResult->addCriticalError("Report_Name is invalid for Report_ID '{$reportId}'",
                    "Report_Name '{$name}' is invalid for Report_ID '{$reportId}'", $position, $data,
                    "must be '{$reportNameForId}'");
            }
        }
        $this->headers['Report_Name'] = $reportNameForId;
    }

    protected function parseInstitutionIds($position, $institutionIds)
    {
        if (! $this->isNonEmptyArray($position, 'Institution_ID', $institutionIds)) {
            return;
        }

        $permittedIdentifiers = $this->config->getIdentifiers('Institution', $this->getFormat());
        $checkedInstitutionIds = $this->checkedIdentifiers($position, 'Institution_ID', $institutionIds,
            $permittedIdentifiers);
        if (isset($checkedInstitutionIds['Invalid'])) {
            // ignore invalid institution identifiers
            unset($checkedInstitutionIds['Invalid']);
        }
        if (! empty($checkedInstitutionIds)) {
            $this->headers['Institution_ID'] = $checkedInstitutionIds;
        }
    }

    protected function parseReportAttributes($position, $attributes)
    {
        $permittedAttributes = $this->config->getReportAttributes($this->getReportId(), $this->getFormat());
        $checkedAttributes = $this->checkedReportAttributeFilters($position, $attributes, $permittedAttributes);
        if (! empty($checkedAttributes)) {
            $this->headers['Report_Attributes'] = $checkedAttributes;
        }
    }

    protected function parseReportFilters($position, $filters)
    {
        $permittedFilters = $this->config->getReportFilters($this->getReportId());
        $checkedFilters = $this->checkedReportAttributeFilters($position, $filters, $permittedFilters);
        $checkedFilters = $this->checkedYopFilters($checkedFilters);

        if (! $this->isMasterReport()) {
            $checkedFilters = $this->checkedRequiredReportFilters($position, $checkedFilters, $permittedFilters);
        }
        $checkedFilters = $this->checkedDateFilters($position, $checkedFilters);

        $this->headers['Report_Filters'] = $checkedFilters;
    }

    protected function getObjectProperties($position, $element, &$object, $required, $optional = [])
    {
        if (! is_object($object)) {
            if ($position === null) {
                $position = 'Report';
                $element = 'Report';
            }
            $message = "{$element} must be an object";
            $this->checkResult->addCriticalError($message, $message, $position, $element);
            return [
                'Invalid' => [
                    'not_an_object' => $object
                ]
            ];
        }

        $permittedProperties = array_merge($required, $optional);
        sort($permittedProperties);

        $properties = [];
        foreach ($object as $name => &$value) {
            if ($this->getFormat() === self::FORMAT_JSON) {
                $positionName = ($position !== null ? "{$position}.{$name}" : $name);
            } else {
                $positionName = $name;
            }
            $data = $this->formatData($element, $name);
            if ($value === null) {
                $message = "Null value for property '{$name}' is invalid";
                $this->checkResult->addCriticalError($message, $message, $positionName, $data);
                $value = 'null';
            }
            if (in_array($name, $permittedProperties)) {
                $properties[$name] = &$value;
            } elseif (($correctName = $this->inArrayLax($name, $permittedProperties)) !== false) {
                $message = "Spelling of property '{$name}' is wrong";
                $this->checkResult->addError($message, $message, $positionName, $data,
                    "must be spelled '{$correctName}'");
                $properties[$correctName] = &$value;
            } else {
                if ($element === 'Report_Items' &&
                    $this->config->isAttributesToShow($this->getReportId(), $this->getFormat(), $name)) {
                    $message = "Property '{$name}' is not included in Attributes_To_Show and therefore invalid";
                } else {
                    $message = "Property '{$name}' is invalid";
                }
                $this->checkResult->addError($message, $message, $positionName, $data,
                    "permitted properties are '" . implode("', '", $permittedProperties) . "'");
                if (! isset($properties['Invalid'])) {
                    $properties['Invalid'] = [];
                }
                $properties['Invalid'][$name] = $value;
            }
        }

        foreach ($required as $name) {
            if (! array_key_exists($name, $properties)) {
                $message = "Required property '{$name}' is missing";
                $this->checkResult->addCriticalError($message, $message, $position, $element);
            }
        }

        return $properties;
    }

    protected function getKeyValues($position, $element, $array, $keyName, $valueName, $permittedKeys)
    {
        $keyValues = [
            'Invalid' => []
        ];

        if (! is_array($array)) {
            $message = "{$element} must be an array";
            $this->checkResult->addCriticalError($message, $message, $position, $element);
            $keyValues['Invalid']['not_an_array'] = $array;
            return $keyValues;
        }

        sort($permittedKeys);

        foreach ($array as $index => $object) {
            $positionIndex = ($this->getFormat() === self::FORMAT_JSON ? "{$position}[{$index}]" : $position);
            $keyValue = $this->getObjectProperties($positionIndex, $element, $object, [
                $keyName,
                $valueName
            ]);
            if (isset($keyValue['Invalid'])) {
                $keyValues['Invalid'][$index] = $keyValue['Invalid'];
                unset($keyValue['Invalid']);
            }

            $key = null;
            if (isset($keyValue[$keyName])) {
                $positionIndexKeyName = "{$positionIndex}.{$keyName}";
                if (($keyValue[$keyName] = $this->checkedIsString($positionIndexKeyName, $keyName, $keyValue[$keyName])) ===
                    false) {
                    $keyValues['Invalid'][$index] = array_merge($keyValues['Invalid'][$index] ?? [], $keyValue);
                    continue;
                }
                $data = $this->formatData($keyName, $keyValue[$keyName]);
                if (trim($keyValue[$keyName]) === '') {
                    $message = "{$keyName} must not be empty";
                    $this->checkResult->addCriticalError($message, $message, $positionIndexKeyName, $data);
                    $keyValues['Invalid'][$index] = array_merge($keyValues['Invalid'][$index] ?? [], $keyValue);
                } else {
                    $key = $keyValue[$keyName];
                    if (! in_array($key, $permittedKeys)) {
                        if (($correctKey = $this->inArrayLax($key, $permittedKeys)) !== false) {
                            $message = "Spelling of {$keyName} '{$key}' is wrong";
                            $this->checkResult->addError($message, $message, $positionIndexKeyName, $data,
                                "must be spelled '{$correctKey}'");
                            $key = $correctKey;
                        } else {
                            $message = "{$keyName} '{$key}' is invalid";
                            $this->checkResult->addError($message, $message, $positionIndexKeyName, $data,
                                "permitted {$keyName}s are '" . implode("', '", $permittedKeys) . "'");
                            $keyValues['Invalid'][$index] = array_merge($keyValues['Invalid'][$index] ?? [], $keyValue);
                            $key = null;
                        }
                    }
                }
            }

            $value = null;
            if (isset($keyValue[$valueName])) {
                $positionIndexValueName = "{$positionIndex}.{$valueName}";
                if (($keyValue[$valueName] = $this->checkedIsString($positionIndexValueName, $valueName,
                    $keyValue[$valueName])) === false) {
                    $keyValues['Invalid'][$index] = array_merge($keyValues['Invalid'][$index] ?? [], $keyValue);
                    continue;
                }
                $data = $this->formatData($valueName, $keyValue[$valueName]);
                if (trim($keyValue[$valueName]) !== $keyValue[$valueName]) {
                    $this->checkResult->addError("{$valueName} value includes whitespace",
                        "{$valueName} value '" . $keyValue[$valueName] . "' includes whitespace",
                        $positionIndexValueName, $data);
                    $keyValue[$valueName] = trim($keyValue[$valueName]);
                }
                if ($keyValue[$valueName] === '') {
                    $message = "{$valueName} must not be empty";
                    $this->checkResult->addError($message, $message, $positionIndexValueName, $data);
                    $keyValues['Invalid'][$index] = array_merge($keyValues['Invalid'][$index] ?? [], $keyValue);
                } else {
                    $value = $keyValue[$valueName];
                }
            }

            if ($key === null || $value === null) {
                $keyValues['Invalid'][$index] = array_merge($keyValues['Invalid'][$index] ?? [], $keyValue);
                continue;
            }

            if (! isset($keyValues[$key])) {
                $keyValues[$key] = [];
            }
            $keyValues[$key][] = [
                'v' => $value,
                'p' => $positionIndex
            ];
        }

        if (empty($keyValues['Invalid'])) {
            unset($keyValues['Invalid']);
        }

        return $keyValues;
    }

    protected function parseNotEmpty($position, $element, $value, $context)
    {
        $elementName = $this->getElementName($element, $context);
        $current = 'current' . $context;

        if (($value = $this->checkedIsString($position, $elementName, $value)) === false) {
            return;
        }

        $data = $this->formatData($elementName, $value);
        if (trim($value) === '') {
            $message = "{$elementName} must not be empty";
            $this->checkResult->addCriticalError($message, $message, $position, $data);
            return;
        }
        if (trim($value) !== $value) {
            $addLevel = ($element === 'YOP' ? 'addError' : 'addWarning');
            $this->checkResult->$addLevel("{$elementName} value includes whitespace",
                "{$elementName} value '{$value}' includes whitespace", $position, $data);
            $value = trim($value);
        }

        $this->$current[$element] = $value;
    }

    protected function parseDatabase($position, $element, $database, $context)
    {
        $this->parseNotEmpty($position, $element, $database, $context);

        // TODO: check Database filter
    }

    protected function parseItemTitle($position, $element, $publisher, $context)
    {
        $elementName = $this->getElementName($element, $context);
        $current = 'current' . $context;

        if (($value = $this->checkedIsString($position, $elementName, $publisher)) === false) {
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

    protected function parsePublisher($position, $element, $publisher, $context)
    {
        $elementName = $this->getElementName($element, $context);
        $current = 'current' . $context;

        if (($value = $this->checkedIsString($position, $elementName, $publisher)) === false) {
            return;
        }

        $data = $this->formatData($elementName, $value);
        if (trim($value) === '') {
            $message = "{$elementName} is missing which may affect the audit result";
            $this->checkResult->addWarning($message, $message, $position, $data,
                'please see Section 3.3.10 of the Code of Practice for details');
            $value = trim($value);
        }
        if (trim($value) !== $value) {
            $this->checkResult->addWarning("{$elementName} value includes whitespace",
                "{$elementName} value '{$value}' includes whitespace", $position, $data);
            $value = trim($value);
        }

        $this->$current[$element] = $value;
    }

    protected function parsePublisherIds($position, $element, $publisherIds, $context)
    {
        $elementName = $this->getElementName($element, $context);
        $current = 'current' . $context;

        $permittedIdentifiers = $this->config->getIdentifiers('Publisher', $this->getFormat());
        $checkedPublisherIds = $this->checkedIdentifiers($position, $elementName, $publisherIds, $permittedIdentifiers);
        if (empty($checkedPublisherIds)) {
            $message = "{$elementName} must not be empty";
            $this->checkResult->addError($message, $message, $position, $elementName,
                "optional elements without a value must be omitted");
        } else {
            if (isset($checkedPublisherIds['Invalid'])) {
                // ignore invalid publisher identifiers
                unset($checkedPublisherIds['Invalid']);
            }
            if (! empty($checkedPublisherIds)) {
                $this->$current[$element] = $checkedPublisherIds;
            }
        }
    }

    protected function parsePlatform($position, $element, $value, $context)
    {
        $current = 'current' . $context;

        $this->parseNotEmpty($position, $element, $value, $context);

        if (isset($this->$current[$element]) && ! in_array($this->$current[$element], $this->platforms)) {
            $this->platforms[] = $this->$current[$element];
        }

        // TODO: check Platform filter (after parsing all rows/Report_Items?)
    }

    protected function parseYop($position, $element, $value, $context)
    {
        $elementName = $this->getElementName($element, $context);
        $current = 'current' . $context;

        $this->parseNotEmpty($position, $element, $value, $context);

        if (isset($this->$current[$element])) {
            $yop = $this->$current[$element];
            $data = $this->formatData($elementName, $value);
            if (! preg_match('/^[0-9]{1,4}$/', $yop) || (int) $yop === 0) {
                $this->checkResult->addCriticalError("{$elementName} value is invalid",
                    "{$elementName} value is invalid", $position, $data, "{$elementName} must be in the range 0001-9999");
                $this->addInvalid($context, $element, $value);
                unset($this->$current[$element]);
                return;
            }
            if (strlen($yop) !== 4) {
                $message = "Leading zero(s) missing for {$elementName} value";
                $this->checkResult->addError($message, $message . " value '{$yop}'", $position, $data,
                    'format must be yyyy');
                $this->$current[$element] = sprintf("%04d", $yop);
            }

            // TODO: check YOP filter
        }
    }

    protected function parseEnumeratedElement($position, $element, $value, $context)
    {
        $elementName = $this->getElementName($element, $context);
        $current = 'current' . $context;

        if (($value = $this->checkedIsString($position, $elementName, $value)) === false) {
            return;
        }

        if ($element === 'Data_Type' && $context !== 'Item') {
            // Parent_Data_Type and Component_Data_Type require special handling...
            if (! isset($this->values["{$context}_Data_Type"])) {
                throw new \Exception(
                    "System Error - parseEnumeratedElement: value list missing for {$context}_Data_Type");
            }
            $elementValues = $this->values["{$context}_Data_Type"];
        } else {
            if (! isset($this->values[$element])) {
                throw new \Exception("System Error - parseEnumeratedElement: value list missing for {$elementName}");
            }
            $elementValues = $this->values[$element];
        }

        if ($element === 'Section_Type') {
            // Section_Type must be checked against Data_Type and Metric_Type later, so we store the position
            $this->currentSectionTypePosition = $position;
            if ($this->getFormat() === self::FORMAT_TABULAR && $value === '') {
                // empty Section_Type is valid in tabular reports for Unique_Title metrics, this is checked later
                return;
            }
        }

        if (! in_array($value, $elementValues)) {
            $data = $this->formatData($elementName, $value);
            if (($correctValue = $this->inArrayLax($value, $elementValues)) !== false) {
                $message = "Spelling of {$elementName} value '{$value}' is wrong";
                $this->checkResult->addError($message, $message, $position, $data, "must be spelled '{$correctValue}'");
                $value = $correctValue;
            } else {
                if ($value === '') {
                    $message = "{$elementName} must not be empty";
                    $this->checkResult->addCriticalError($message, $message, $position, $data,
                        "permitted values are '" . implode("', '", $elementValues) . "'");
                } else {
                    $this->checkResult->addCriticalError("{$elementName} value is invalid",
                        "{$elementName} value '{$value}' is invalid", $position, $data,
                        "permitted values are '" . implode("', '", $elementValues) . "'");
                    if ($element === 'Metric_Type' && $this->getFormat() === self::FORMAT_JSON) {
                        $this->addInvalid($context, 'Performance', [
                            $value => [
                                $this->currentDate => null
                            ]
                        ]);
                    } else {
                        $this->addInvalid($context, $element, $value);
                    }
                }
                return;
            }
        }

        if ($element === 'Data_Type' && $value === 'Unspecified') {
            $message = "Using Data_Type 'Unspecified' may affect the audit result";
            $data = $this->formatData($elementName, $value);
            $this->checkResult->addWarning($message, $message, $position, $data,
                'please see Section 3.3.10 of the Code of Practice for details');
        }

        // Full_Content_Databases may optionally provide TR, therefore Data_Type Database has to be permitted in TR,
        // but a Notice is added when Database is used in TR so that auditors can easily spot and check this case
        if ($this->getReportId() === 'TR' && $element === 'Data_Type' && $value === 'Database') {
            $message = "Data_Type 'Database' is only permitted for Full_Content_Databases";
            $data = $this->formatData($elementName, $value);
            $this->checkResult->addWarning($message, $message, $position, $data);
        }

        $this->$current[$element] = $value;
    }
}
