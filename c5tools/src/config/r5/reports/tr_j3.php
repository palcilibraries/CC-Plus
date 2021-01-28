<?php
return [
    'ID' => 'TR_J3',
    'Name' => 'Journal Usage by Access Type',
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
        'Access_Type',
        'Metric_Type',
        'Reporting_Period_Total',
        'Performance'
    ],
    'Filters' => [
        'Access_Method' => [
            'Regular'
        ],
        'Access_Type' => [
            'Controlled',
            'OA_Gold'
        ],
        'Begin_Date',
        'End_Date',
        'Data_Type' => [
            'Journal'
        ],
        'Metric_Type' => [
            'Total_Item_Investigations',
            'Total_Item_Requests',
            'Unique_Item_Investigations',
            'Unique_Item_Requests'
        ],
        'Platform'
    ]
];
