<?php
return [
    'ID' => 'IR_M1',
    'Name' => 'Multimedia Item Requests',
    'MasterReport' => 'IR',
    'Elements' => [
        'Item',
        'Item_ID', // TODO: restrict to permitted elements
        'Publisher',
        'Publisher_ID',
        'Platform',
        'DOI',
        'Proprietary_ID',
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
            'Multimedia'
        ],
        'Metric_Type' => [
            'Total_Item_Requests'
        ],
        'Platform'
    ]
];
