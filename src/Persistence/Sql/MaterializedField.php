<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Atk4\Core\WarnDynamicPropertyTrait;
use Atk4\Data\Field;
use Atk4\Data\Model;

/**
 * TODO shortName should be used by DSQL automatically when in GROUP BY, HAVING, ...
 */
class MaterializedField implements Expressionable
{
    use WarnDynamicPropertyTrait;

    protected Field $field;

    public function __construct(Model $context, Field $field)
    {
        $field->getOwner()->assertIsModel($context);

        $this->field = $field;
    }

    public function getDsqlExpression(Expression $expression): Expression
    {
        return $expression->expr('{}', [$this->field->shortName]);
    }
}
