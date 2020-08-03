<?php

declare(strict_types=1);

namespace atk4\data\Persistence;

use atk4\data\Model;

abstract class AbstractQuery implements \IteratorAggregate
{
    /** @var Model */
    protected $model;

    protected $scope;

    protected $order = [];

    protected $limit = [];

    public function __construct(Model $model)
    {
        $this->model = $model;

        $this->scope = clone $model->scope();

        $this->order = $model->order;

        $this->limit = $model->limit;
    }

    abstract public function find($id): ?array;

    abstract public function select($fields = null): self;

    abstract public function update();

    abstract public function insert();

    abstract public function delete(): self;

    abstract public function exists(): self;

    abstract public function where($fieldName, $operator, $value): self;

    public function order($field, $desc = null): self
    {
        $this->order[] = [$field, $desc];

        return $this;
    }

    public function limit($limit, $offset = 0): self
    {
        $this->limit = [$limit, $offset];

        return $this;
    }

    protected function getLimitArgs()
    {
        if ($this->limit) {
            $offset = $this->limit[1] ?? 0;
            $limit = $this->limit[0] ?? null;

            if ($limit || $offset) {
                if ($limit === null) {
                    $limit = PHP_INT_MAX;
                }

                return [$limit, $offset ?? 0];
            }
        }
    }

    abstract public function execute(): iterable;

    abstract public function get(): array;

    abstract public function count($alias = null);

    abstract public function getRow(): ?array;

    abstract public function getOne();

    abstract public function aggregate($fx, $field, string $alias = null, bool $coalesce = false);

    abstract public function getDebug(): string;
}
