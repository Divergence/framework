<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Data\Collections\Factory;

use Exception;
use Divergence\Data\Collections\Collection;
use Divergence\Data\Collections\Events\Add;
use Divergence\Data\Collections\Events\AddMany;
use Divergence\Data\Collections\Events\Remove;
use Divergence\Data\Collections\Events\RemoveMany;
use Divergence\Data\Collections\Factory\Getters\GetByField;
use Divergence\Data\Collections\Factory\Getters\GetAllByField;
use Divergence\Data\Collections\Factory\Getters\GetByCriteria;
use Divergence\Data\Collections\Factory\Getters\GetAllByCriteria;
use Divergence\Data\Collections\Indexing\CreateIndexByField;
use Divergence\Data\Collections\Indexing\HasIndex;
use Divergence\Data\Collections\Indexing\UpdateIndexForModel;
use Divergence\Data\Collections\Indexing\SetIndexes;
use Divergence\Data\Collections\Indexing\ClearIndexes;

class Factory
{
    protected $getterClasses = [];

    public function __construct()
    {
        $this->registerGetterClasses();
    }

    protected function registerGetterClasses(): void
    {
        $this->getterClasses = [];

        foreach ([
            GetByField::class,
            GetAllByField::class,
            GetByCriteria::class,
            GetAllByCriteria::class,
        ] as $className) {
            $this->registerGetterClass($className);
        }
    }

    protected function registerGetterClass(string $className): void
    {
        $parts = explode('\\', $className);
        $getterName = strtolower(lcfirst(end($parts)));

        if (isset($this->getterClasses[$getterName])) {
            throw new Exception(sprintf('Getter method collision for %s', $getterName));
        }

        $this->getterClasses[$getterName] = $className;
    }

    public function getGetterClasses(): array
    {
        return $this->getterClasses;
    }

    public function create(array $records = [], array $indexes = []): Collection
    {
        Collection::$addHandler = Add::class;
        Collection::$addManyHandler = AddMany::class;
        Collection::$removeHandler = Remove::class;
        Collection::$removeManyHandler = RemoveMany::class;
        Collection::$createIndexByFieldHandler = CreateIndexByField::class;
        Collection::$hasIndexHandler = HasIndex::class;
        Collection::$updateIndexForModelHandler = UpdateIndexForModel::class;
        Collection::$setIndexesHandler = SetIndexes::class;
        Collection::$clearIndexesHandler = ClearIndexes::class;

        return new Collection($records, $indexes);
    }
}
