<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Array_\Db;

abstract class TableAbstract
{
    abstract public function hasRow(RefRow $refRow): bool;

    abstract public function getRow(RefRow $refRow): RowAbstract;

    /**
     * @return \Traversable<RowAbstract>
     */
    abstract public function getRows(): \Traversable;
}
