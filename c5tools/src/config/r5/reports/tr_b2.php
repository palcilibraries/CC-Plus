<?php
return [
    'ID' => 'TR_B2',
    'Name' => 'Book Access Denied',
    'MasterReport' => 'TR',
    'Elements' => [
        'Title',
        'Item_ID',
        'Publisher',
        'Publisher_ID',
        'Platform',
        'DOI',
        'Proprietary_ID',
        'ISBN',
        'Print_ISSN',
        'Online_ISSN',
        'URI',
        'YOP',
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
            'Book'
        ],
        'Metric_Type' => [
            'Limit_Exceeded',
            'No_License'
        ],
        'Platform'
    ]
];
