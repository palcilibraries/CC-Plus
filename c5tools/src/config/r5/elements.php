<?php
return [
    'json' => [
        'Institution_Name' => [
            'parse' => 'parseNotEmpty'
        ],
        'Customer_ID' => [
            'parse' => 'parseNotEmpty'
        ],
        'Database' => [
            'required' => true,
            'parse' => 'parseDatabase'
        ],
        'Title' => [
            'required' => true,
            'parse' => 'parseItemTitle'
        ],
        'Item' => [
            'required' => true,
            'parse' => 'parseItemTitle'
        ],
        'Item_ID' => [
            'parse' => 'parseItemIdentifiers'
        ],
        'Item_Contributors' => [
            'parse' => 'parseItemContributors'
        ],
        'Item_Dates' => [
            'parse' => 'parseItemDates'
        ],
        'Item_Attributes' => [
            'parse' => 'parseItemAttributes'
        ],
        'Publisher' => [
            'required' => true,
            'parse' => 'parsePublisher'
        ],
        'Publisher_ID' => [
            'parse' => 'parsePublisherIds'
        ],
        'Platform' => [
            'required' => true,
            'parse' => 'parsePlatform'
        ],
        'Item_Parent' => [
            'parse' => 'parseItemParent',
            'elements' => [
                'Item_Name' => [
                    'required' => true,
                    'parse' => 'parseItemName'
                ],
                'Item_ID' => [
                    'parse' => 'parseItemIdentifiers'
                ],
                'Item_Contributors' => [
                    'parse' => 'parseItemContributors'
                ],
                'Item_Dates' => [
                    'parse' => 'parseItemDates'
                ],
                'Item_Attributes' => [
                    'parse' => 'parseItemAttributes'
                ],
                'Data_Type' => [
                    'required' => true,
                    'parse' => 'parseEnumeratedElement'
                ]
            ]
        ],
        'Item_Component' => [
            'parse' => 'parseItemComponent',
            'elements' => [
                'Item_Name' => [
                    'required' => true,
                    'parse' => 'parseItemName'
                ],
                'Item_ID' => [
                    'parse' => 'parseItemIdentifiers'
                ],
                'Item_Contributors' => [
                    'parse' => 'parseItemContributors'
                ],
                'Item_Dates' => [
                    'parse' => 'parseItemDates'
                ],
                'Item_Attributes' => [
                    'parse' => 'parseItemAttributes'
                ],
                'Data_Type' => [
                    'required' => true,
                    'parse' => 'parseEnumeratedElement'
                ],
                'Performance' => [
                    'required' => true,
                    'parse' => 'parsePerformance'
                ]
            ]
        ],
        'Data_Type' => [
            'required' => true,
            'parse' => 'parseEnumeratedElement'
        ],
        'Section_Type' => [
            'parse' => 'parseEnumeratedElement'
        ],
        'YOP' => [
            'required' => true,
            'parse' => 'parseYop'
        ],
        'Access_Type' => [
            'required' => true,
            'parse' => 'parseEnumeratedElement'
        ],
        'Access_Method' => [
            'required' => true,
            'parse' => 'parseEnumeratedElement'
        ],
        'Performance' => [
            'required' => true,
            'parse' => 'parsePerformance'
        ]
    ],
    'tabular' => [
        'Institution_Name' => [
            'parse' => 'parseNotEmpty'
        ],
        'Customer_ID' => [
            'parse' => 'parseNotEmpty'
        ],
        'Database' => [
            'parse' => 'parseDatabase'
        ],
        'Title' => [
            'parse' => 'parseItemTitle'
        ],
        'Item' => [
            'parse' => 'parseItemTitle'
        ],
        'Publisher' => [
            'parse' => 'parsePublisher'
        ],
        'Publisher_ID' => [
            'parse' => 'parsePublisherIds'
        ],
        'Platform' => [
            'parse' => 'parsePlatform'
        ],
        'Authors' => [
            'parse' => 'parseAuthors'
        ],
        'Publication_Date' => [
            'parse' => 'parsePublicationDate'
        ],
        'Article_Version' => [
            'parse' => 'parseArticleVersion'
        ],
        'DOI' => [
            'parse' => 'parseItemIdentifier'
        ],
        'Proprietary_ID' => [
            'parse' => 'parseItemIdentifier'
        ],
        'ISBN' => [
            'parse' => 'parseItemIdentifier'
        ],
        'Print_ISSN' => [
            'parse' => 'parseItemIdentifier'
        ],
        'Online_ISSN' => [
            'parse' => 'parseItemIdentifier'
        ],
        'URI' => [
            'parse' => 'parseItemIdentifier'
        ],
        'Parent_Title' => [
            'parse' => 'parseParentComponentTitle'
        ],
        'Parent_Authors' => [
            'parse' => 'parseAuthors'
        ],
        'Parent_Publication_Date' => [
            'parse' => 'parsePublicationDate'
        ],
        'Parent_Article_Version' => [
            'parse' => 'parseArticleVersion'
        ],
        'Parent_Data_Type' => [
            'parse' => 'parseParentComponentDataType'
        ],
        'Parent_DOI' => [
            'parse' => 'parseItemIdentifier'
        ],
        'Parent_Proprietary_ID' => [
            'parse' => 'parseItemIdentifier'
        ],
        'Parent_ISBN' => [
            'parse' => 'parseItemIdentifier'
        ],
        'Parent_Print_ISSN' => [
            'parse' => 'parseItemIdentifier'
        ],
        'Parent_Online_ISSN' => [
            'parse' => 'parseItemIdentifier'
        ],
        'Parent_URI' => [
            'parse' => 'parseItemIdentifier'
        ],
        'Component_Title' => [
            'parse' => 'parseParentComponentTitle'
        ],
        'Component_Authors' => [
            'parse' => 'parseAuthors'
        ],
        'Component_Publication_Date' => [
            'parse' => 'parsePublicationDate'
        ],
        'Component_Data_Type' => [
            'parse' => 'parseParentComponentDataType'
        ],
        'Component_DOI' => [
            'parse' => 'parseItemIdentifier'
        ],
        'Component_Proprietary_ID' => [
            'parse' => 'parseItemIdentifier'
        ],
        'Component_ISBN' => [
            'parse' => 'parseItemIdentifier'
        ],
        'Component_Print_ISSN' => [
            'parse' => 'parseItemIdentifier'
        ],
        'Component_Online_ISSN' => [
            'parse' => 'parseItemIdentifier'
        ],
        'Component_URI' => [
            'parse' => 'parseItemIdentifier'
        ],
        'Data_Type' => [
            'parse' => 'parseEnumeratedElement'
        ],
        'Section_Type' => [
            'parse' => 'parseEnumeratedElement'
        ],
        'YOP' => [
            'parse' => 'parseYop'
        ],
        'Access_Type' => [
            'parse' => 'parseEnumeratedElement'
        ],
        'Access_Method' => [
            'parse' => 'parseEnumeratedElement'
        ],
        'Metric_Type' => [
            'parse' => 'parseEnumeratedElement'
        ],
        'Reporting_Period_Total' => [
            'parse' => 'parseReportingPeriodTotal'
        ]
    ]
];
