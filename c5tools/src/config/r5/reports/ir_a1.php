<?php
return [
    'ID' => 'IR_A1',
    'Name' => 'Journal Article Requests',
    'MasterReport' => 'IR',
    'Elements' => [
        'Item',
        'Item_ID', // TODO: restrict to permitted elements
        'Item_Contributors', // TODO: restrict to permitted elements
        'Item_Dates', // TODO: restrict to permitted elements
        'Item_Attributes', // TODO: restrict to permitted elements
        'Publisher',
        'Publisher_ID',
        'Platform',
        'Item_Parent',
        'Authors',
        'Publication_Date',
        'Article_Version',
        'DOI',
        'Proprietary_ID',
        'Print_ISSN',
        'Online_ISSN',
        'URI',
        'Parent_Title',
        'Parent_Authors',
        'Parent_Article_Version',
        'Parent_DOI',
        'Parent_Proprietary_ID',
        'Parent_Print_ISSN',
        'Parent_Online_ISSN',
        'Parent_URI',
        'Access_Type',
        'Metric_Type',
        'Reporting_Period_Total',
        'Performance'
    ],
    'Item_Parent' => [
        'Item_Name',
        'Item_ID',
        'Item_Contributors',
        'Item_Attributes'
    ],
    'Filters' => [
        'Access_Method' => [
            'Regular'
        ],
        'Access_Type' => [
            'Controlled',
            'OA_Gold',
            'Other_Free_To_Read'
        ],
        'Begin_Date',
        'End_Date',
        'Data_Type' => [
            'Article'
        ],
        'Parent_Data_Type' => [
            'Journal'
        ],
        'Metric_Type' => [
            'Total_Item_Requests',
            'Unique_Item_Requests'
        ],
        'Platform'
    ]
];
