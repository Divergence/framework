<?php
namespace Divergence\Models;

class Model extends ActiveRecord
{
    public static $fields = [
        'ID' => [
            'type' => 'integer'
            ,'autoincrement' => true
            ,'unsigned' => true,
        ]
        ,'Class' => [
            'type' => 'enum'
            ,'notnull' => true
            ,'values' => [],
        ]
        ,'Created' => [
            'type' => 'timestamp'
            ,'default' => 'CURRENT_TIMESTAMP',
        ]
        ,'CreatorID' => [
            'type' => 'integer'
            ,'notnull' => false,
        ],
    ];
}
