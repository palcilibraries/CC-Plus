<?php
return [
    'ID' => 'DR_D2',
    'Name' => 'Database Access Denied',
    'MasterReport' => 'DR',
    'Elements' => [
        'Database',
        'Item_ID',
        'Publisher',
        'Publisher_ID',
        'Platform',
        'Proprietary_ID',
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
        'Metric_Type' => [
            'Limit_Exceeded',
            'No_License'
        ],
        'Platform'
    ]
];
