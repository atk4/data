<?php

declare(strict_types=1);

namespace Atk4\Data\Model\Scope;

use Atk4\Data\Exception;
use Atk4\Data\Model;

/**
 * The root scope object used in the Model::$scope property
 * All other conditions of the Model object are elements of the root scope
 * Scope elements are joined always using AND junction.
 */
class RootScope extends Model\Scope
{
    /** @var Model */
    protected $model;

    protected function __construct(array $conditions = [])
    {
        parent::__construct($conditions, self::AND);
    }

    /**
     * @return $this
     */
    public function setModel(Model $model)
    {
        $model->assertIsModel();

        if ($this->model !== $model) {
            $this->model = $model;

            $this->onChangeModel();
        }

        return $this;
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function negate()
    {
        throw new Exception('Model scope cannot be negated');
    }

    /**
     * @return Model\Scope
     */
    public static function createAnd(...$conditions) // @phpstan-ignore-line
    {
        return (parent::class)::createAnd(...$conditions);
    }

    /**
     * @return Model\Scope
     */
    public static function createOr(...$conditions) // @phpstan-ignore-line
    {
        return (parent::class)::createOr(...$conditions);
    }
}
