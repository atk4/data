<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Core\WarnDynamicPropertyTrait;
use Atk4\Data\Field;
use Atk4\Data\Model;

/**
 * @template-covariant TModel of Model
 * @template-covariant TField of Field
 */
class EntityFieldPair
{
    use WarnDynamicPropertyTrait;

    /** @var TModel */
    private $entity;
    /** @var string */
    private $fieldName;

    /**
     * @param TModel $entity
     */
    public function __construct(Model $entity, string $fieldName)
    {
        $entity->assertIsEntity();

        $this->entity = $entity;
        $this->fieldName = $fieldName;
    }

    /**
     * @return TModel
     */
    public function getModel(): Model
    {
        return $this->entity->getModel();
    }

    /**
     * @return TModel
     */
    public function getEntity(): Model
    {
        return $this->entity;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * @phpstan-return TField
     */
    public function getField(): Field
    {
        $field = $this->getModel()->getField($this->getFieldName());

        return $field;
    }

    /**
     * @return mixed
     */
    public function get()
    {
        return $this->getEntity()->get($this->getFieldName());
    }

    /**
     * @param mixed $value
     */
    public function set($value): void
    {
        $this->getEntity()->set($this->getFieldName(), $value);
    }

    public function setNull(): void
    {
        $this->getEntity()->setNull($this->getFieldName());
    }
}
