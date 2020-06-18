<?php
/**
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
 * {@inheritDoc}
 */
class Model extends ActiveRecord
{
    /**
     * {@inheritDoc}
     */
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
