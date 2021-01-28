<?php
return [
    'ID' => 'TR_J2',
    'Name' => 'Journal Access Denied',
    'MasterReport' => 'TR',
    'Elements' => [
        'Title',
        'Item_ID',
        'Publisher',
        'Publisher_ID',
        'Platform',
        'DOI',
        'Proprietary_ID',
        'Print_ISSN',
        'Online_ISSN',
        'URI',
        'Metric_Type',
        'Reporting_Period_Total',
        'Performance'
    ],
    'Filters' => [
        'Access_Method' => [
            'Regular'
        ],
        'Begin_Date',
        'End_Date',
        'Data_Type' => [
            'Journal'
        ],
        'Metric_Type' => [
            'Limit_Exceeded',
            'No_License'
        ],
        'Platform'
    ]
];
