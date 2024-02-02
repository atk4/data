<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Core\TrackableTrait;
use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

/**
 * Map self::action() to self::getOwner()->actionInnerTable().
 *
 * Called from https://github.com/atk4/data/blob/5.0.0/src/Persistence/Sql.php#L188.
 *
 * @method Model getOwner()
 *
 * @internal
 */
class UnionInternalTable
{
    use TrackableTrait;

    /**
     * @param array<mixed> $args
     *
     * @return Persistence\Sql\Query
     */
    public function action(string $mode, array $args = [])
    {
        if ($mode !== 'select' || $args !== []) {
            throw new Exception('Only "select" action with empty arguments is expected');
        }

        $model = $this->getOwner();

        $tableOrig = $model->table;
        $model->table = '_tu';
        try {
            return $model->actionSelectInnerTable(); // @phpstan-ignore-line
        } finally {
            $model->table = $tableOrig;
        }
    }
}
