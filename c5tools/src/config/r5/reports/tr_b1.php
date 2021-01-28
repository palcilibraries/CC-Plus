<?php
return [
    'ID' => 'TR_B1',
    'Name' => 'Book Requests (Excluding OA_Gold)',
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
        'Access_Type' => [
            'Controlled'
        ],
        'Begin_Date',
        'End_Date',
        'Data_Type' => [
            'Book'
        ],
        'Metric_Type' => [
            'Total_Item_Requests',
            'Unique_Title_Requests'
        ],
        'Platform'
    ]
];
