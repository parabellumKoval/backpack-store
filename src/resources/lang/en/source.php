<?php

return [
    'item' => [
        'label' => 'Path to item',
        'hint' => 'Path to item in data source.'
    ],
    'fields_setup' => [
        'title' => 'Fields Setup',
        'xml_description' => 'For "XML-link" data type:',
        'xml_instruction' => 'Enter exact field names from the XML catalog that correspond to the specified data.',
        'xml_purpose' => 'Required to establish correspondence between data fields from the XML catalog with similar fields on the site.',
        'file_description' => 'For "File" data type:',
        'file_instruction' => 'Specify the letter of the column from the excel file where the corresponding data is located.'
    ],
    'name' => [
        'label' => 'Name',
        'type' => 'text'
    ],
    'code' => [
        'label' => 'Article',
        'type' => 'text'
    ],
    'barcode' => [
        'label' => 'Code/barcode',
        'type' => 'text'
    ],
    'image' => [
        'label' => 'Image',
        'type' => 'text'
    ]
];