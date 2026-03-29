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

use Divergence\Models\Mapping\Column;

/**
 * Model.
 *
 * @author Henry Paradiz <henry.paradiz@gmail.com>
 *
 * {@inheritDoc}
 * @method static static|null getByContextObject(ActiveRecord $Record, $options = [])
 * @method static static|null getByContext($contextClass, $contextID, $options = [])
 * @method static static|null getByHandle($handle)
 * @method static static|null getByID($id)
 * @method static static|null getByField($field, $value, $cacheIndex = false)
 * @method static array<string, mixed>|null getRecordByField($field, $value, $cacheIndex = false)
 * @method static static|null getByWhere($conditions, $options = [])
 * @method static array<string, mixed>|null getRecordByWhere($conditions, $options = [])
 * @method static static|null getByQuery($query, $params = [])
 * @method static array<array-key, static> getAllByClass($className = false, $options = [])
 * @method static array<array-key, static> getAllByContextObject(ActiveRecord $Record, $options = [])
 * @method static array<array-key, static> getAllByContext($contextClass, $contextID, $options = [])
 * @method static array<array-key, static> getAllByField($field, $value, $options = [])
 * @method static array<array-key, static> getAllByWhere($conditions = [], $options = [])
 * @method static array<array-key, static> getAll($options = [])
 * @method static array<array-key, array<string, mixed>>|array<string, array<string, mixed>> getAllRecords($options = [])
 * @method static array<array-key, static> getAllByQuery($query, $params = [])
 * @method static array<array-key, static> getTableByQuery($keyField, $query, $params = [])
 * @method static array<array-key, array<string, mixed>>|array<string, array<string, mixed>> getAllRecordsByWhere($conditions = [], $options = [])
 * @method static string getUniqueHandle($text, $options = [])
 */
class Model extends ActiveRecord
{
    use Getters;

    #[Column(type: "integer", primary:true, autoincrement:true, unsigned:true)]
    private int $ID;

    #[Column(type: "enum", values:[])]
    private string $Class;

    #[Column(type: "timestamp", default:'CURRENT_TIMESTAMP')]
    private string $Created;

    #[Column(type: "integer")]
    private ?int $CreatorID;
}
