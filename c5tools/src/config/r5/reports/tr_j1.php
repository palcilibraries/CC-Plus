<?php
return [
    'ID' => 'TR_J1',
    'Name' => 'Journal Requests (Excluding OA_Gold)',
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
        'Access_Type' => [
            'Controlled'
        ],
        'Begin_Date',
        'End_Date',
        'Data_Type' => [
            'Journal'
        ],
        'Metric_Type' => [
            'Total_Item_Requests',
            'Unique_Item_Requests'
        ],
        'Platform'
    ]
];
