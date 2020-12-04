<?php

declare(strict_types=1);

namespace Atk4\Data\Model\Scope;

use atk4\data\Exception;
use atk4\data\Model;

/**
 * The root scope object used in the Model::$scope property
 * All other conditions of the Model object are elements of the root scope
 * Scope elements are joined only using AND junction.
 */
class RootScope extends Model\Scope
{
    /**
     * @var Model
     */
    protected $model;

    protected function __construct(array $nestedConditions = [])
    {
        parent::__construct($nestedConditions, self::AND);
    }

    public function setModel(Model $model)
    {
        if ($this->model !== $model) {
            $this->model = $model;

            $this->onChangeModel();
        }

        return $this;
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function negate()
    {
        throw new Exception('Model Scope cannot be negated!');
    }

    /**
     * @return static
     */
    public static function createAnd(...$conditions)
    {
        return (parent::class)::createAnd(...$conditions);
    }

    /**
     * @return static
     */
    public static function createOr(...$conditions)
    {
        return (parent::class)::createOr(...$conditions);
    }
}
