<?php
// Order is relevant, see comments below!
return [
    'Release' => [
        'json' => 'required',
        'row' => 3,
        'check' => 'checkedRelease'
    ],
    'Report_ID' => [
        'json' => 'required',
        'row' => 2,
        'parse' => 'parseReportId'
    ],
    'Report_Name' => [
        // check requires Report_ID
        'json' => 'required',
        'row' => 1,
        'parse' => 'parseReportName'
    ],
    'Created' => [
        'json' => 'required',
        'row' => 11,
        'check' => 'checkedCreated'
    ],
    'Created_By' => [
        'json' => 'required',
        'row' => 12,
        'check' => 'checkedHeaderNotEmpty'
    ],
    'Customer_ID' => [
        'json' => 'required',
        'check' => 'checkedHeaderNotEmpty'
    ],
    'Exceptions' => [
        'json' => 'optional',
        'row' => 9,
        'parse' => 'parseExceptions'
    ],
    'Institution_ID' => [
        'json' => 'optional',
        'row' => 5,
        'parse' => 'parseInstitutionIds'
    ],
    'Institution_Name' => [
        'json' => 'required',
        'row' => 4,
        'check' => 'checkedHeaderNotEmpty'
    ],
    'Metric_Types' => [
        // must be parsed and checked before Report_Filters
        'row' => 6,
        'parse' => 'parseMetricTypes'
    ],
    'Reporting_Period' => [
        // must be parsed and checked before Report_Filters
        'row' => 10,
        'parse' => 'parseReportingPeriod'
    ],
    'Report_Attributes' => [
        'json' => 'optional',
        'row' => 8,
        'parse' => 'parseReportAttributes'
    ],
    'Report_Filters' => [
        'json' => 'required',
        'row' => 7,
        'parse' => 'parseReportFilters'
    ]
];
