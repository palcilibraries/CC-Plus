<?php
namespace ubfr\c5tools;

trait Checks
{

    protected function checkedIsString($position, $element, $value)
    {
        if (! is_string($value)) {
            $message = "{$element} must be a string";
            if (! is_scalar($value)) {
                $this->checkResult->addCriticalError($message, $message, $position, $element);
                return false;
            }
            $data = $this->formatData($element, $value);
            $this->checkResult->addError($message, $message, $position, $data);
            $value = (string) $value;
        }
        return $value;
    }

    protected function checkedCreated($position, $element, $created)
    {
        if (($created = $this->checkedIsString($position, $element, $created)) === false) {
            return false;
        }

        $data = $this->formatData($element, $created);
        if (trim($created) === '') {
            $message = "{$element} date is missing";
            $this->checkResult->addError($message, $message, $position, $data);
            return false;
        }
        if (trim($created) !== $created) {
            $this->checkResult->addError("{$element} date includes whitespace",
                "{$element} date '{$created}' includes whitespace", $position, $data);
            $created = trim($created);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.[0-9]{3}[Z+-]/', $created)) {
            // format is correct except for the milliseconds
            $message = "Format is wrong for {$element}";
            $this->checkResult->addError($message, $message . " value '{$created}'", $position, $data,
                'the date must be in RFC3339 date-time format without milliseconds (yyyy-mm-ddThh:mm:ssZ)');
            $created = substr($created, 0, 19) . substr($created, 23);
        }
        $datetime = \DateTime::createFromFormat(\DateTime::RFC3339, $created);
        // DateTime accepts lower case T and Z, so we also check the format via preg_match
        if ($datetime === false || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[Z+-]/', $created)) {
            $message = "Format is wrong for {$element}";
            $this->checkResult->addError($message, $message . " value '{$created}'", $position, $data,
                'the date must be in RFC3339 date-time format (yyyy-mm-ddThh:mm:ssZ)');
            return false;
        }
        if ($datetime !== false) {
            $errors = $datetime->getLastErrors();
            $messages = implode('. ', $errors['errors']) . implode('. ', $errors['warnings']);
            if ($messages !== '') {
                $this->checkResult->addError("{$element} date is invalid",
                    "{$element} date '{$created}' is invalid: {$messages}", $position, $data);
                return false;
            }
        }

        return $created;
    }

    protected function checkedHeaderNotEmpty($position, $element, $value)
    {
        if (($value = $this->checkedIsString($position, $element, $value)) === false) {
            return false;
        }

        $data = $this->formatData($element, $value);
        $value = trim($value);
        if ($value === '') {
            $message = "{$element} must not be empty";
            // method is used for Created_By, Customer_ID and Institution_Name, so this is not critical
            $this->checkResult->addError($message, $message, $position, $data);
            return false;
        }
        return $value;
    }

    protected function checkedRelease($position, $element, $release)
    {
        if (($release = $this->checkedIsString($position, $element, $release)) === false) {
            return false;
        }

        if (trim($release) !== $release) {
            $data = $this->formatData($element, $release);
            $this->checkResult->addError("{$element} value includes whitespace",
                "{$element} value '{$release}' includes whitespace", $position, $data);
            $release = trim($release);
        }

        if ($release != $this->release) {
            $message = "{$element} must be '{$this->release}'";
            $this->checkResult->fatalError($message, $message);
            return false;
        }
        return $release;
    }

    protected function checkedException($position, $element, $exception)
    {
        // TODO
        return $exception;
    }

    protected function checkedIdentifiers($position, $element, $identifiers, $permittedIdentifiers)
    {
        $checkIdentifiers = $this->getKeyValues($position, $element, $identifiers, 'Type', 'Value',
            array_keys($permittedIdentifiers));
        if (isset($checkIdentifiers['Invalid'])) {
            $invalid = $checkIdentifiers['Invalid'];
            unset($checkIdentifiers['Invalid']);
        } else {
            $invalid = [];
        }

        $checkedIdentifiers = [];
        foreach ($checkIdentifiers as $identifierName => $valuePositions) {
            if (! isset($permittedIdentifiers[$identifierName]['check'])) {
                throw new \Exception("System Error - Config: check missing for item identifier {$element}");
            }
            $check = $permittedIdentifiers[$identifierName]['check'];
            $multi = (isset($permittedIdentifiers[$identifierName]['multi']) &&
                $permittedIdentifiers[$identifierName]['multi'] === true);
            $checkedValues = [];
            foreach ($valuePositions as $index => $valuePosition) {
                $position = $valuePosition['p'];
                $data = $this->formatData("{$element} {$identifierName}", $valuePosition['v']);
                if ($index > 0) {
                    if ($multi) {
                        $hint = 'multiple values must be separated by a pipe character ("|")';
                    } else {
                        $hint = "ignoring previous {$identifierName} value";
                        $checkedValues = [];
                    }
                    $message = "Identifier {$identifierName} specified multiple times";
                    $this->checkResult->addError($message, $message, $position, $data, $hint);
                }
                if ($multi) {
                    $values = explode('|', $valuePosition['v']);
                } else {
                    $values = [
                        $valuePosition['v']
                    ];
                }
                foreach ($values as $value) {
                    if (trim($value) !== $value) {
                        $this->checkResult->addError("{$identifierName} value includes whitespace",
                            "{$identifierName} value '{$value}' includes whitespace", $position, $data);
                        $value = trim($value);
                    }
                    $checkedValue = $this->$check($position, $identifierName, $value);
                    if ($checkedValue !== false) {
                        $checkedValues[] = $checkedValue;
                    } else {
                        $invalid[] = [
                            'Type' => $identifierName,
                            'Value' => $value
                        ];
                    }
                }
            }
            if (! empty($checkedValues)) {
                $checkedIdentifiers[$identifierName] = ($multi ? $checkedValues : $checkedValues[0]);
            }
        }

        if (! empty($invalid)) {
            $checkedIdentifiers['Invalid'] = $invalid;
        }

        return $checkedIdentifiers;
    }

    protected function checkedIsilIdentifier($position, $element, $isil)
    {
        if (! preg_match('/^([A-Z]{2}|[a-zA-Z0-9]{1,3,4})-.{1,11}/', $isil)) {
            $data = $this->formatData($element, $isil);
            $this->checkResult->addError("ISIL value is invalid", "ISIL value is invalid", $position, $data,
                "ISIL must consist of a (non-)country code followed by a hyphen and a unit identifier with upto 11 characters");
            return false;
        }
        return $isil;
    }

    protected function checkedIsniIdentifier($position, $element, $isni)
    {
        if (! preg_match('/^[0-9]{4}[ -]?[0-9]{4}[ -]?[0-9]{4}[ -]?[0-9]{3}[0-9X]$/', $isni)) {
            $data = $this->formatData($element, $isni);
            $this->checkResult->addError("ISNI value is invalid", "ISNI value is invalid", $position, $data,
                "ISNI must consist of 15 digits and a digit or X with optional spaces or hyphens between blocks of four");
            return false;
        }
        return $isni;
    }

    protected function checkedOrcidIdentifier($position, $element, $orcid)
    {
        if (! preg_match('/^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{3}[0-9X]$/', $orcid)) {
            $data = $this->formatData($element, $orcid);
            $this->checkResult->addError("ORCID value is invalid", "ORCID value is invalid", $position, $data,
                "ORCID must consist of 15 digits and a digit or X with hyphens between blocks of four");
            return false;
        }
        return $orcid;
    }

    protected function checkedOclcIdentifier($position, $element, $oclc)
    {
        if (! preg_match('/^[0-9]+$/', $oclc)) {
            $data = $this->formatData($element, $oclc);
            $this->checkResult->addError("OCLC value is invalid", "OCLC value is invalid", $position, $data,
                "must be digits only");
            return false;
        }
        return $oclc;
    }

    protected function checkedProprietaryIdentifier($position, $element, $proprietary)
    {
        $message = 'Proprietary identifier is invalid';
        $data = $this->formatData($element, $proprietary);
        if (! preg_match('/^[^:]+:.+$/', $proprietary)) {
            $this->checkResult->addError($message, $message, $position, $data,
                "the identifier must include the platform ID of the host that assigned the identifier followed by a colon");
            return false;
        }
        $matches = [];
        if (! preg_match('/^([a-zA-Z0-9+_.\/]+):/', $proprietary, $matches)) {
            $this->checkResult->addError($message, $message, $position, $data,
                "the platform ID must only consist of a-z, A-Z, 0-9, underscore, dot and forward slash");
        } elseif (strlen($matches[1]) > 17) {
            $this->checkResult->addError($message, $message, $position, $data,
                "the maximum length allowed for the platform ID is 17 characters");
        }
        return $proprietary;
    }

    protected function checkedDoiIdentifier($position, $element, $doi)
    {
        $data = $this->formatData($element, $doi);
        if (preg_match('/^10\.[1-9][0-9]{3}[0-9.]*\/.+$/', $doi)) {
            return $doi;
        }
        $matches = [];
        if (preg_match('/\b(10\.[1-9][0-9]{3}[0-9.]*\/.+)$/', $doi, $matches)) {
            $message = "Format is wrong for {$element}";
            $this->checkResult->addError($message, $message, $position, $data,
                "{$element} must only contain a DOI in format {DOI prefix}/{DOI suffix}");
            return $matches[1];
        }
        $this->checkResult->addError("{$element} value is invalid", "{$element} value is invalid", $position, $data,
            "must be a DOI in format {DOI prefix}/{DOI suffix}");
        return false;
    }

    protected function checkedIsbnIdentifier($position, $element, $isbn)
    {
        $data = $this->formatData($element, $isbn);
        $isbnNoHyphens = str_replace('-', '', $isbn);
        if (preg_match('/^97[89][0-9]{10}$/', $isbnNoHyphens)) {
            if (strlen($isbn) !== 17) {
                $message = "Format is wrong for {$element}";
                $this->checkResult->addError($message, $message, $position, $data,
                    strlen($isbn) < 17 ? 'hyphens are missing' : 'too many hyphens');
            }
            return $isbn;
        }
        if (preg_match('/^[0-9]{13}$/', $isbnNoHyphens)) {
            $this->checkResult->addError("{$element} value is invalid", "{$element} value is invalid", $position, $data,
                "must be an ISBN-13 starting with prefix 978 or 979");
            return false;
        }
        if (preg_match('/^[0-9]{9}[0-9xX]$/', $isbnNoHyphens)) {
            $message = "Format ISBN-10 is wrong for {$element}";
            $this->checkResult->addError($message, $message, $position, $data, 'format must be ISBN-13 with hyphens');
            return $this->getIsbn13($isbn);
        }
        $matches = [];
        if (preg_match('/^(97[89][0-9-]{13})\b/', $isbn, $matches) ||
            preg_match('/^(97[89][0-9]{10})\b/', $isbn, $matches)) {
            $message = "Format is wrong for {$element}";
            $this->checkResult->addError($message, $message, $position, $data,
                "{$element} must only contain an ISBN-13 with hyphens");
            return $matches[1];
        }
        if (preg_match('/^([0-9-]{12}[0-9xX])\b/', $isbn, $matches) ||
            preg_match('/^([0-9]{9}[0-9xX])\b/', $isbn, $matches)) {
            $message = "Format ISBN-10 is wrong for {$element}";
            $this->checkResult->addError($message, $message, $position, $data,
                "{$element} must only contain an ISBN-13 with hyphens");
            return $this->getIsbn13($matches[1]);
        }
        $this->checkResult->addError("{$element} value is invalid", "{$element} value is invalid", $position, $data,
            "must be an ISBN-13 with hyphens");
        return false;
    }

    protected function checkedIssnIdentifier($position, $element, $issn)
    {
        $data = $this->formatData($element, $issn);
        $matches = [];
        if (preg_match('/^[0-9]{4}-?[0-9]{3}[0-9xX]$/', $issn)) {
            $message = "Format is wrong for {$element}";
            if (strlen($issn) === 8) {
                $this->checkResult->addError($message, $message, $position, $data, 'hyphen is missing');
                $issn = substr($issn, 0, 4) . '-' . substr($issn, 4, 4);
            }
            if (substr($issn, - 1, 1) === 'x') {
                $this->checkResult->addError($message, $message, $position, $data, 'x must be in upper case');
                $issn = strtoupper($issn);
            }
            return $issn;
        }
        if (preg_match('/\b([0-9]{4})-?([0-9]{3}[0-9xX])\b/', $issn, $matches)) {
            $message = "Format is wrong for {$element}";
            $this->checkResult->addError($message, $message, $position, $data,
                "{$element} must only contain an ISSN in format nnnn-nnn[nX]");
            return $matches[1] . '-' . strtoupper($matches[2]);
        }
        $this->checkResult->addError("{$element} value is invalid", "{$element} value is invalid", $position, $data,
            "must be an ISSN in format nnnn-nnn[nX]");
        return false;
    }

    protected function checkedUriIdentifier($position, $element, $uri)
    {
        $data = $this->formatData($element, $uri);
        if (($urlComponents = parse_url($uri)) !== false) {
            // URLs are only accepted if they have at least a scheme and a host
            // RFC3986 allows more schemes, but in a report only http(s) and ftp make sense
            if (isset($urlComponents['scheme']) && in_array($urlComponents['scheme'], [
                'http',
                'https',
                'ftp'
            ]) && isset($urlComponents['host']) && preg_match('/^([^.]+\.)+[^.]{2,}$/', $urlComponents['host'])) {
                return $uri;
            }
        }
        if (preg_match('/^urn:[a-zA-Z0-9][a-zA-Z0-9-]{0,30}[a-zA-Z0-9]:.+/', $uri)) {
            // URNs are accepted if they have a correct NID, the NSS isn't checked
            return $uri;
        }
        $this->checkResult->addError("{$element} value is invalid", "{$element} value is invalid", $position, $data,
            "must be a valid URL or URN in RFC3986 format");
        return false;
    }

    protected function checkedReportAttributeFilters($position, $attributeFilters, $permittedAttributeFilters)
    {
        $checkedAttributeFilters = [];
        foreach ($attributeFilters as $attributeFilterName => $valuePositions) {
            $permitted = $permittedAttributeFilters[$attributeFilterName];
            $multi = (isset($permitted['multi']) && $permitted['multi'] === true);
            $checkedValues = [];
            foreach ($valuePositions as $index => $valuePosition) {
                $position = $valuePosition['p'];
                $data = $this->formatData($attributeFilterName, $valuePosition['v']);
                if ($index > 0) {
                    $message = "{$attributeFilterName} specified multiple times";
                    if ($multi) {
                        $hint = 'multiple values must be separated by a pipe character ("|")';
                    } else {
                        $hint = "ignoring previous {$attributeFilterName} value";
                        $checkedValues = [];
                    }
                    $this->checkResult->addError($message, $message, $position, $data, $hint);
                }
                if ($multi) {
                    $values = explode('|', $valuePosition['v']);
                } else {
                    $values = [
                        $valuePosition['v']
                    ];
                }
                if (isset($permitted['values'])) {
                    foreach ($values as $valueIndex => $value) {
                        $positionIndex = ($this->getFormat() === self::FORMAT_JSON ? "{$position}[{$valueIndex}]" : $position);
                        if ($value === '') {
                            $message = "{$attributeFilterName} must not be empty";
                            $this->checkResult->addError($message, $message, $positionIndex, $data);
                            continue;
                        }
                        $checkedValue = null;
                        if (in_array($value, $permitted['values'])) {
                            $checkedValue = $value;
                        } elseif (($correctValue = $this->inArrayLax($value, $permitted['values']))) {
                            $this->checkResult->addError("Spelling of {$attributeFilterName} value is wrong",
                                "Spelling of {$attributeFilterName} value '{$value}' is wrong", $positionIndex, $data,
                                "must be spelled '{$correctValue}'");
                            $checkedValue = $correctValue;
                        } else {
                            if ($attributeFilterName === 'Metric_Type' && $this->getFormat() === self::FORMAT_TABULAR) {
                                $separator = 'semicolon-space';
                            } else {
                                $separator = 'pipe';
                            }
                            $this->checkResult->addError("{$attributeFilterName} value is invalid",
                                "{$attributeFilterName} value '{$value}' is invalid", $positionIndex, $data,
                                "permitted values are '" . implode("', '", $permitted['values']) . "'" .
                                ($multi ? " (multiple values separated by {$separator})" : ''));
                        }
                        if ($checkedValue !== null) {
                            if (in_array($checkedValue, $checkedValues)) {
                                $this->checkResult->addError("{$attributeFilterName} value specified multiple times",
                                    "{$attributeFilterName} value '{$value}' specified multiple times", $positionIndex,
                                    $data);
                            } else {
                                $checkedValues[] = $checkedValue;
                            }
                        }
                    }
                } else {
                    $checkedValues[] = ($multi ? $values : $values[0]);
                }
            }

            if (empty($checkedValues)) {
                continue;
            }

            if (isset($permitted['default'])) {
                if ($permitted['default'] === 'All') {
                    $default = $permitted['values'];
                } else {
                    $default = [
                        $permitted['default']
                    ];
                }
                if (array_diff($checkedValues, $default) === array_diff($default, $checkedValues)) {
                    $data = $this->formatData($attributeFilterName, implode('|', $checkedValues));
                    $this->checkResult->addError("{$attributeFilterName} value is the default and must be omitted",
                        "{$attributeFilterName} value '" . implode('|', $checkedValues) .
                        "' is the default and must be omitted", $position, $data);
                    continue;
                }
            }
            if (! $multi && $this->lax($checkedValues[0]) === 'all') {
                $data = $this->formatData($attributeFilterName, $checkedValues[0]);
                $this->checkResult->addError(
                    "Value 'All' must no be used to indicate that all {$attributeFilterName}s are included in the report",
                    "Value '{$checkedValues[0]}' must no be used to indicate that all {$attributeFilterName}s are included in the report",
                    $position, $data, "instead {$attributeFilterName} must be omitted");
                continue;
            }

            // position is only stored for the last occurrence of the attribute/filter to keep things a bit easier
            $checkedAttributeFilters[$attributeFilterName] = [
                'v' => ($multi ? $checkedValues : $checkedValues[0]),
                'p' => $position
            ];
        }

        return $checkedAttributeFilters;
    }

    protected function checkedRequiredReportFilters($position, $filters, $requiredFilters)
    {
        foreach ($filters as $filterName => $filterValuePosition) {
            if (! isset($requiredFilters[$filterName]['multi'])) {
                continue;
            }
            $missingValues = array_diff($requiredFilters[$filterName]['values'], $filterValuePosition['v']);
            foreach ($missingValues as $missingValue) {
                $message = "{$filterName} value {$missingValue} required for this report is missing";
                $data = $this->formatData($filterName, implode('|', $filterValuePosition['v']));
                $this->checkResult->addCriticalError($message, $message, $filterValuePosition['p'], $data);
                $filters[$filterName]['v'][] = $missingValue;
            }
        }

        foreach ($requiredFilters as $requiredFilterName => $requiredFilterInfo) {
            if (! isset($requiredFilterInfo['multi'])) {
                continue;
            }
            if (! isset($filters[$requiredFilterName])) {
                $message = "Report_Filters '{$requiredFilterName}' required for this report is missing";
                $this->checkResult->addCriticalError($message, $message, $position, 'Report_Filters');
            }
        }

        return $filters;
    }

    protected function checkedDateFilters($position, $filters)
    {
        foreach ([
            'Begin_Date',
            'End_Date'
        ] as $filter) {
            if (isset($filters[$filter])) {
                $checkedDate = $this->checkedDate($filters[$filter]['p'], $filter, $filters[$filter]['v']);
                if ($checkedDate === false) {
                    unset($filters[$filter]);
                } else {
                    $filters[$filter]['v'] = $checkedDate;
                }
            } else {
                $message = "Report_Filters '{$filter}' required for this report is missing";
                $this->checkResult->addCriticalError($message, $message, $position, 'Report_Filters');
            }
        }

        if (isset($filters['Begin_Date']) && isset($filters['End_Date'])) {
            if ($filters['End_Date']['v'] < $filters['Begin_Date']['v']) {
                $this->checkResult->addCriticalError('End_Date is before Begin_Date',
                    "End_Date '" . $filters['End_Date']['v'] . "' is before Begin_Date '" . $filters['Begin_Date']['v'] .
                    "'", $filters['End_Date']['p'], $position, 'Report_Filters');
                unset($filters['Begin_Date']);
                unset($filters['End_Date']);
            }
        }

        return $filters;
    }

    protected function checkedDate($position, $element, $date)
    {
        if (($date = $this->checkedIsString($position, $element, $date)) === false) {
            return false;
        }

        $data = $this->formatData($element, $date);
        if (trim($date) !== $date) {
            $this->checkResult->addError("{$element} value includes whitespace",
                "{$element} value '{$date}' includes whitespace", $position, $data);
            $date = trim($date);
        }
        if (preg_match('/^[0-9]{4}-[0-9]{2}$/', $date)) {
            $message = "Format is wrong for {$element}";
            $firstLast = ($element === 'Begin_Date' ? ' first' : ($element === 'End_Date' ? ' last' : ''));
            $this->checkResult->addError($message, $message . " value '{$date}'", $position, $data,
                "the date must include the{$firstLast} day of the month");
            $format = 'Y-m';
        } elseif (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date)) {
            $format = 'Y-m-d';
        } else {
            // try automatic conversion with strtotime
            $timestamp = strtotime($date);
            if ($timestamp !== false) {
                $summary = "Format is wrong for {$element}";
                $message = $summary . " value '{$date}'";
                $format = 'Y-m-d';
                $date = date($format, $timestamp);
                // correct dates too far in the future
                $year = substr($date, 0, 4);
                if ($year > date('Y') + 1) {
                    $date = ($year - 100) . substr($date, 4);
                }
                $message .= " (converted to '{$date}')";
                $addLevel = 'addError';
            } else {
                $summary = "{$element} value is invalid";
                $message = "{$element} value '{$date}' is invalid";
                $addLevel = 'addCriticalError';
            }
            $this->checkResult->$addLevel($summary, $message, $position, $data, 'the date must be in yyyy-mm-dd format');
            return ($timestamp === false ? false : $date);
        }
        $datetime = \DateTime::createFromFormat($format, $date);
        if ($format === 'Y-m') {
            // Bug in DateTime? Parsing 2019-02 with Y-m results in 2019-03-01 during DST.
            // To make sure the month is correct by substracting a few days.
            $datetime->modify('-5 day');
        }
        if ($datetime === false || $date !== $datetime->format($format)) {
            $this->checkResult->addCriticalError("{$element} value is invalid", "{$element} value '{$date}' is invalid",
                $position, $data);
            return false;
        }
        $errors = $datetime->getLastErrors();
        $messages = implode('. ', $errors['errors']) . implode('. ', $errors['warnings']);
        if ($messages !== '') {
            $this->checkResult->addCriticalError("{$element} value is invalid",
                "{$element} value '{$date}' is invalid: {$messages}", $position, $data);
            return false;
        }
        if ($format === 'Y-m') {
            if ($element === 'End_Date') {
                $date = $datetime->format('Y-m-t');
            } else {
                $date .= '-01';
            }
        }
        if ($element === 'Begin_Date' && substr($date, - 2) !== '01') {
            $this->checkResult->addError("{$element} value is invalid", "{$element} value '{$date}' is invalid",
                $position, $data, "day must be the first day of the month");
            $date = $datetime->format("Y-m-01");
        }
        if ($element === 'End_Date' && substr($date, - 2) !== $datetime->format('t')) {
            $this->checkResult->addError("{$element} value is invalid", "{$element} value '{$date}' is invalid",
                $position, $data, "day must be the last day of the month");
            $date = $datetime->format("Y-m-t");
        }
        return $date;
    }

    protected function checkedYopFilters($filters)
    {
        if (! isset($filters['YOP'])) {
            return $filters;
        }

        $checkedValues = [];
        $position = $filters['YOP']['p'];
        foreach ($filters['YOP']['v'] as $values) {
            $data = $this->formatData('YOP', implode('|', $values));
            foreach ($values as $index => $value) {
                $positionIndex = ($this->getFormat() === self::FORMAT_JSON ? "{$position}.Value[{$index}]" : $position);
                if (trim($value) === '') {
                    $message = 'YOP filter must not be empty';
                    $this->checkResult->addError($message, $message, $positionIndex, $data);
                    continue;
                }
                if (trim($value) !== $value) {
                    $this->checkResult->addError('YOP filter includes whitespace',
                        "YOP filter '{$value}' includes whitespace", $positionIndex, $data);
                    $value = trim($value);
                }
                $matches = [];
                if (! preg_match('/^([0-9]{4})(?:-([0-9]{4}))?$/', $value, $matches)) {
                    $this->checkResult->addCriticalError('YOP filter is invalid', "YOP filter '{$value}' is invalid",
                        $positionIndex, $data, 'value must be a year (yyyy) or a range of years (yyyy-yyyy)');
                    continue;
                }
                $yearFrom = $matches[1];
                $yearTo = (count($matches) === 3 ? $matches[2] : $matches[1]);
                if ($yearFrom === '0000') {
                    $message = "YOP 0000 is not a valid year, using 0001 instead";
                    $this->checkResult->addError($message, $message, $positionIndex, $data);
                    $yearFrom = '0001';
                    if ($yearTo === '0000') {
                        $yearTo = '0001';
                    }
                }
                if ($yearFrom > $yearTo) {
                    $this->checkResult->addCriticalError('YOP filter is not a valid range of years',
                        "YOP filter '{$value}' is not a valid range of years", $positionIndex, $data);
                    continue;
                }
                if (isset($checkedValues[$yearFrom])) {
                    // merge with existing range starting with the same year
                    if ($yearTo >= $checkedValues[$yearFrom]) {
                        $checkedValues[$yearFrom] = $yearTo;
                    }
                } else {
                    $checkedValues[$yearFrom] = $yearTo;
                }
            }
        }

        if (empty($checkedValues)) {
            unset($filters['YOP']);
            return $filters;
        }

        // merge overlapping ranges
        ksort($checkedValues);
        $lastFrom = null;
        $lastTo = null;
        foreach ($checkedValues as $from => $to) {
            if ($lastFrom === null) {
                $lastFrom = $from;
                $lastTo = $to;
                continue;
            }
            if ($from <= $lastTo + 1) {
                if ($to > $lastTo) {
                    $checkedValues[$lastFrom] = $to;
                    $lastTo = $to;
                }
                unset($checkedValues[$from]);
                continue;
            }
            $lastFrom = $from;
            $lastTo = $to;
        }

        if (count($checkedValues) === 1 && isset($checkedValues['0001']) && $checkedValues['0001'] = '9999') {
            $message = "YOP filter value '0001-9999' is the default and must be omitted";
            $this->checkResult->addError($message, $message, $position, "YOP '0001-9999'");
            unset($filters['YOP']);
        } else {
            $filters['YOP']['v'] = $checkedValues;
        }

        return $filters;
    }

    protected function checkedArticleVersion($position, $element, $value, $context)
    {
        static $articleVersions = [
            'AM' => 'Accepted Manuscript',
            'VoR' => 'Version of Record',
            'CVoR' => 'Corrected Version of Record',
            'EVoR' => 'Enhanced Version of Record'
        ];

        if (($value = $this->checkedIsString($position, $element, $value)) === false) {
            return false;
        }

        $elementName = $this->getElementName($element, $context);
        $data = $this->formatData($elementName, $value);
        foreach ($articleVersions as $code => $description) {
            if ($value === $code) {
                return $value;
            }
            if ($this->lax($value) === $this->lax($code) || $this->lax($value) === $this->lax($description)) {
                $this->checkResult->addError("Spelling of {$elementName} value is wrong",
                    "Spelling of {$elementName} value '{$value}' is wrong", $position, $data,
                    "must be spelled '{$code}'");
                return $code;
            }
        }

        $this->checkResult->addError("{$elementName} value is invalid", "{$elementName} value is invalid", $position,
            $data, "permitted values are '" . implode("', '", array_keys($articleVersions)) . "'");

        return false;
    }

    protected function checkedAuthorIdentifier($position, $element, $authorString, $identifierString)
    {
        static $permittedIdentifiers = null;

        if ($permittedIdentifiers === null) {
            $permittedIdentifiers = $this->config->getIdentifiers('Author', $this->getFormat());
        }

        $data = $this->formatData($element, $authorString);
        $identifierParts = explode(':', $identifierString, 2);
        if (count($identifierParts) === 1 || $identifierParts[0] === '') {
            $message = "Namespace is missing for {$element}";
            $this->checkResult->addError($message, $message . " value '{$identifierString}'", $position, $data,
                "format of {$element} must be {namespace}:{value}");
            return false;
        } else {
            $type = $identifierParts[0];
            $identifier = $identifierParts[1];
            $correctType = $this->inArrayLax($type, array_keys($permittedIdentifiers));
            if ($correctType === false) {
                $this->checkResult->addError("{$element} namespace is invalid",
                    "{$element} namespace '{$type}' is invalid", $position, $data,
                    "permitted namespaces are '" . implode("', '", array_keys($permittedIdentifiers)) . "'");
                return false;
            }
            if ($type !== $correctType) {
                $this->checkResult->addError("Spelling of {$element} namespace is wrong",
                    "Spelling of {$element} namespace '{$type}' is wrong", $position, $data,
                    "must be spelled '{$correctType}'");
                $type = $correctType;
            }
            if (! isset($permittedIdentifiers[$type]['check'])) {
                throw new \Exception("System Error - Config: check missing for author identifier {$type}");
            }
            $check = $permittedIdentifiers[$type]['check'];
            $checkedIdentifier = $this->$check($position, $element, $identifier);
            if ($checkedIdentifier === false) {
                return false;
            }
            return "{$type}:{$checkedIdentifier}";
        }
    }

    protected function checkItemTitleIdentifiers($position)
    {
        $itemNameElement = $this->getItemNameElement('Item');
        if ($itemNameElement !== 'Item' && $itemNameElement !== 'Title') {
            return;
        }

        if (isset($this->currentItem[$itemNameElement]) && $this->currentItem[$itemNameElement] !== '') {
            // non-empty Item or Title present, so everything is fine
            return;
        }

        $data = "{$itemNameElement} ''";
        if (! isset($this->currentItem['Item_ID']) || empty($this->currentItem['Item_ID'])) {
            $message = "Both {$itemNameElement} and {$itemNameElement} identifiers are missing";
            $this->checkResult->addCriticalError($message, $message, $position, $data);
        } else {
            $message = "{$itemNameElement} is missing which may affect the audit result";
            $this->checkResult->addWarning($message, $message, $position, $data,
                'please see Section 3.3.10 of the Code of Practice for details');
        }
    }

    protected function checkSectionType($position)
    {
        // TODO: check Section_Type against Data_Type
    }

    protected function checkMetrics()
    {
        if (empty($this->items)) {
            return;
        }

        $this->checkMetricTypeNotPresent();
        $this->checkItemMetrics();
        $this->checkTitleMetrics();
    }

    protected function checkMetricTypeNotPresent()
    {
        $metricTypeNotPresent = array_diff($this->values['Metric_Type'], array_keys($this->metricTypePresent));
        if (! empty($metricTypeNotPresent)) {
            $message = "Metric_Type(s) valid in this report but not present: '" . implode("', '", $metricTypeNotPresent) .
                "'";
            $position = ($this->getFormat() === self::FORMAT_JSON ? 'Report_Items' : $this->numberOfRows + 1);
            $this->checkResult->addNotice($message, $message, $position, 'Metric_Type');
        }
    }

    protected function checkItemMetrics()
    {
        static $compareItemMetrics = [
            [
                'Total_Item_Investigations',
                'Unique_Item_Investigations'
            ],
            [
                'Total_Item_Investigations',
                'Total_Item_Requests'
            ],
            [
                'Total_Item_Investigations',
                'Unique_Item_Requests'
            ],
            [
                'Unique_Item_Investigations',
                'Unique_Item_Requests'
            ],
            [
                'Total_Item_Requests',
                'Unique_Item_Requests'
            ]
        ];

        foreach ($this->items as $item) {
            $performance = null;
            if (isset($item['Item_Component'])) {
                $aggregatedPerformance = [];
                foreach ($item['Item_Component'] as $component) {
                    if (! $this->hasMetricType($component, '/_Item_/')) {
                        continue;
                    }
                    $performance = $this->getPerformanceByDate($component['Performance']);
                    $position = end($component['Positions']);
                    $data = "Item '" . ($item['Item'] ?? '') . "', Component '" . ($component['Item_Name'] ?? '') . "'";
                    if (count($component['Positions']) > 1) {
                        $data .= ' (occurrences: ';
                        if ($this->getFormat() === self::FORMAT_TABULAR) {
                            $data .= 'rows ';
                        }
                        $data .= implode(', ', $component['Positions']) . ')';
                    }
                    $this->compareMetrics($performance, 'Total_Item_Investigations', 'Total_Item_Requests', $position,
                        $data);
                    $this->aggregatePerformance($aggregatedPerformance, $performance);
                }
                if (isset($item['Performance'])) {
                    $performance = array_merge_recursive($this->getPerformanceByDate($item['Performance']),
                        $aggregatedPerformance);
                } else {
                    $performance = $aggregatedPerformance;
                }
            } elseif ($this->hasMetricType($item, '/_Item_/')) {
                $performance = $this->getPerformanceByDate($item['Performance']);
            }
            if ($performance === null) {
                continue;
            }
            $position = end($item['Positions']);
            $itemNameElement = $this->getItemNameElement('Item');
            $data = "{$itemNameElement} '" . ($item[$itemNameElement] ?? '') . "'";
            if (count($item['Positions']) > 1) {
                $data .= ' (occurrences: ';
                if ($this->getFormat() === self::FORMAT_TABULAR) {
                    $data .= 'rows ';
                }
                $data .= implode(', ', $item['Positions']) . ')';
            }
            foreach ($compareItemMetrics as $metrics) {
                $this->compareMetrics($performance, $metrics[0], $metrics[1], $position, $data);
            }
        }
    }

    protected function checkTitleMetrics()
    {
        static $compareTitleMetrics = [
            [
                'Total_Item_Investigations',
                'Unique_Title_Investigations'
            ],
            [
                'Total_Item_Investigations',
                'Unique_Title_Requests'
            ],
            [
                'Unique_Item_Investigations',
                'Unique_Title_Investigations'
            ],
            [
                'Unique_Item_Investigations',
                'Unique_Title_Requests'
            ],
            [
                'Unique_Title_Investigations',
                'Unique_Title_Requests'
            ],
            [
                'Total_Item_Requests',
                'Unique_Title_Requests'
            ],
            [
                'Unique_Item_Requests',
                'Unique_Title_Requests'
            ]
        ];

        if ((isset($this->values['Data_Type']) && ! in_array('Book', $this->values['Data_Type'])) ||
            ! (in_array('Unique_Title_Investigations', $this->values['Metric_Type']) ||
            in_array('Unique_Title_Requests', $this->values['Metric_Type']))) {
            return;
        }
        $isAllBookReport = (isset($this->values['Data_Type']) && count($this->values['Data_Type']) === 1);

        // Unique_Title metrics don't have a Section_Type, so for comparing them with the Total/Unique_Item metrics
        // all items identical execpt for the Section_Type have to be considered, they are collected in a list with
        // the hash without Section_Type as key and the hashes with Section_Types as values
        $hashIgnoreSectionTypes = [];
        foreach ($this->items as $hash => $item) {
            if (isset($item['Section_Type']) || (isset($item['Invalid']) && isset($item['Invalid']['Section_Type']))) {
                $hashIgnoreSectionType = $this->computeHash($item, true);
            } else {
                $hashIgnoreSectionType = $hash;
            }
            if (! isset($hashIgnoreSectionTypes[$hashIgnoreSectionType])) {
                $hashIgnoreSectionTypes[$hashIgnoreSectionType] = [];
            }
            $hashIgnoreSectionTypes[$hashIgnoreSectionType][] = $hash;
        }

        foreach ($hashIgnoreSectionTypes as $hashes) {
            $performance = [];
            $positions = [];
            foreach ($hashes as $hash) {
                // the performance and positions are collected from all items identical except for Section_Type
                $item = $this->items[$hash];
                $positions = array_merge($item['Positions'], $positions);
                if (! isset($item['Performance'])) {
                    continue;
                }
                foreach ($this->getPerformanceByDate($item['Performance']) as $date => $metricCounts) {
                    if (! isset($performance[$date])) {
                        $performance[$date] = $metricCounts;
                    } else {
                        foreach ($metricCounts as $metric => $count) {
                            if (! isset($performance[$date][$metric])) {
                                $performance[$date][$metric] = $count;
                            } else {
                                // when a Total/Unique_Item metric occurs multiple times with different Section_Types
                                // the counts have to be added up to get an upper limit for the Unique_Title metrics
                                // since the counts with the different Section_Types might be from different sessions
                                $performance[$date][$metric] += $count;
                            }
                        }
                    }
                }
            }
            if (empty($performance)) {
                continue;
            }
            sort($positions);
            $position = end($positions);
            $itemNameElement = $this->getItemNameElement('Item');
            $data = "{$itemNameElement} '" . ($item[$itemNameElement] ?? '') . "'";
            if (count($positions) > 1) {
                $data .= ' (occurrences: ';
                if ($this->getFormat() === self::FORMAT_TABULAR) {
                    $data .= 'rows ';
                }
                $data .= implode(', ', $positions) . ')';
            }
            $dataType = ($isAllBookReport ? 'Book' : (isset($item['Data_Type']) ? $item['Data_Type'] : null));
            foreach ($compareTitleMetrics as $metrics) {
                $this->compareMetrics($performance, $metrics[0], $metrics[1], $position, $data, $dataType);
            }
        }
    }

    protected function compareMetrics($performance, $metric1, $metric2, $position, $data, $dataType = null)
    {
        if (! in_array($metric1, $this->values['Metric_Type']) || ! in_array($metric2, $this->values['Metric_Type'])) {
            // nothing to check if one of the metrics isn't included in the report
            return;
        }

        foreach ($performance as $date => $metricCounts) {
            if (! isset($metricCounts[$metric1]) && ! isset($metricCounts[$metric2])) {
                // both metrics not present for this date, nothing to check
                continue;
            }
            $dataWithDate = $data . ", Month {$date}";
            if (! isset($metricCounts[$metric1])) {
                // when metric2 is present, metric1 also must be present
                $message = "{$metric1} is missing while {$metric2} is present";
                $this->checkResult->addCriticalError($message, $message, $position, $dataWithDate);
            } elseif (! isset($metricCounts[$metric2])) {
                // when metric2 is missing, the situation is more complex...
                if ($this->endsWith('_Investigations', $metric1) && $this->endsWith('_Requests', $metric2)) {
                    // when comparing Investigations with Requests
                    // there is no way to determine whether Requests must be present
                    continue;
                }
                if (strpos($metric1, '_Item_') !== false && strpos($metric2, '_Title_') !== false && $dataType !== 'Book') {
                    // when comparing an Item metric with a Title metric (both Investigations or both Requests)
                    // there is no way to determine whether the Title metric must be present
                    // unless we know that the item is a Book
                    continue;
                }
                $message = "{$metric2} is missing while {$metric1} is present";
                $this->checkResult->addCriticalError($message, $message, $position, $dataWithDate);
            } elseif ($metricCounts[$metric1] < $metricCounts[$metric2]) {
                $message = "Less {$metric1} than {$metric2}";
                $this->checkResult->addCriticalError($message, $message, $position,
                    $dataWithDate . ", {$metricCounts[$metric1]} {$metric1} < {$metricCounts[$metric2]} {$metric2}");
            }
        }
    }

    protected function aggregatePerformance(&$aggregatedPerformance, $performance)
    {
        foreach ($performance as $date => $metricCounts) {
            if (! isset($aggregatedPerformance[$date])) {
                $aggregatedPerformance[$date] = $metricCounts;
            } else {
                foreach ($metricCounts as $metric => $count) {
                    if (! isset($aggregatedPerformance[$date][$metric])) {
                        $aggregatedPerformance[$date][$metric] = $count;
                    } else {
                        $aggregatedPerformance[$date][$metric] += $count;
                    }
                }
            }
        }
    }
}
