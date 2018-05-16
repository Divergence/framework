<?php

/*
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Models;

/**
 * Model.
 *
 * @author Henry Paradiz <henry.paradiz@gmail.com>
 *
 * @property   int      ID          Primary Key of this Model
 * @property   string   Class       Name of this fully qualified PHP class for use with subclassing to explicitly specify which class to instantiate a record as when pulling from datastore.
 * @property   mixed    Created     Timestamp of when this record was created. Supports Unix timestamp as well as any format accepted by PHP's strtotime as well as MySQL standard.
 * @property   int      CreatorID   A standard user ID field for use by your login & authentication system.
 * {@inheritDoc}
 */
class Model extends ActiveRecord
{
    public static $fields = [
        'ID' => [
            'type' => 'integer',
            'autoincrement' => true,
            'unsigned' => true,
        ],
        'Class' => [
            'type' => 'enum',
            'notnull' => true,
            'values' => [],
        ],
        'Created' => [
            'type' => 'timestamp',
            'default' => 'CURRENT_TIMESTAMP',
        ],
        'CreatorID' => [
            'type' => 'integer',
            'notnull' => false,
        ],
    ];
}
