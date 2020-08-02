<?php

declare(strict_types=1);

namespace atk4\data\Persistence;

interface QueryInterface extends \IteratorAggregate
{
    public function find($id): ?array;

    public function select($fields = null): self;

    public function update();

    public function insert();

    public function delete(): self;

    public function exists(): self;

//     public function where($fieldName, $operator, $value): self;

    public function order($fields): self;

    public function limit($limit, $offset = 0): self;

    public function get(): array;

    public function count($alias = null);

    public function getRow(): ?array;

    public function getOne();

    public function aggregate($fx, $field, string $alias = null, bool $coalesce = false);
}
