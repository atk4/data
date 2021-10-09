<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

use Atk4\Data\Persistence\Sql\Query as BaseQuery;

abstract class AbstractQuery extends BaseQuery
{
    public function render(): string
    {
        if ($this->mode === 'select' && $this->main_table === null) {
            $this->table('DUAL');
        }

        return parent::render();
    }

    public function groupConcat($field, string $delimiter = ',')
    {
        return $this->expr('listagg({field}, []) within group (order by {field})', ['field' => $field, $delimiter]);
    }
}
