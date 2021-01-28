<?php
return [
    'ID' => 'TR',
    'Name' => 'Title Master Report',
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
        'Metric_Type',
        'Reporting_Period_Total',
        'Performance'
    ],
    'Attributes' => [
        'Attributes_To_Show' => [
            'Institution_Name',
            'Customer_ID',
            'Data_Type',
            'Section_Type',
            'YOP',
            'Access_Type',
            'Access_Method'
        ],
        'Exclude_Monthly_Details',
        'Granularity'
    ],
    'Filters' => [
        'Access_Method' => [
            'Regular',
            'TDM'
        ],
        'Access_Type' => [
            'Controlled',
            'OA_Gold'
        ],
        'Begin_Date',
        'End_Date',
        'Data_Type' => [
            'Book',
            'Database', // Full_Content_Databases only (raises Notice in Parsers::parseEnumeratedElement)
            'Journal',
            'Newspaper_or_Newsletter',
            'Other',
            'Report',
            'Thesis_or_Dissertation',
            'Unspecified'
        ],
        'Item_ID',
        'Metric_Type' => [
            'Total_Item_Investigations',
            'Total_Item_Requests',
            'Unique_Item_Investigations',
            'Unique_Item_Requests',
            'Unique_Title_Investigations',
            'Unique_Title_Requests',
            'Limit_Exceeded',
            'No_License'
        ],
        'Platform',
        'Section_Type' => [
            'Article',
            'Book',
            'Chapter',
            'Other',
            'Section'
        ],
        'YOP'
    ]
];
