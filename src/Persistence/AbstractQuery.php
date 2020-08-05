<?php

declare(strict_types=1);

namespace atk4\data\Persistence;

use atk4\data\Exception;
use atk4\data\Model;

abstract class AbstractQuery implements \IteratorAggregate
{
    public const MODE_SELECT = 'SELECT';
    public const MODE_UPDATE = 'UPDATE';
    public const MODE_INSERT = 'INSERT';
    public const MODE_DELETE = 'DELETE';

    /** @const string */
    public const HOOK_INIT_SELECT = self::class . '@initSelect';
    /** @const string */
    public const HOOK_BEFORE_INSERT = self::class . '@beforeInsert';
    /** @const string */
    public const HOOK_AFTER_INSERT = self::class . '@afterInsert';
    /** @const string */
    public const HOOK_BEFORE_UPDATE = self::class . '@beforeUpdate';
    /** @const string */
    public const HOOK_AFTER_UPDATE = self::class . '@afterUpdate';
    /** @const string */
    public const HOOK_BEFORE_DELETE = self::class . '@beforeDelete';
    /** @const string */
    public const HOOK_BEFORE_EXECUTE = self::class . '@beforeExecute';
    /** @const string */
    public const HOOK_AFTER_EXECUTE = self::class . '@afterExecute';

    /** @var Model */
    protected $model;

    protected $scope;

    protected $order = [];

    protected $limit = [];

    protected $mode;

    public function __construct(Model $model)
    {
        $this->model = clone $model;

        $this->scope = $this->model->scope();

        $this->order = $model->order;

        $this->limit = $model->limit;
    }

    public function find($id): ?array
    {
        return $this->whereId($id)->getRow();
    }

    public function select($fields = null): self
    {
        $this->initWhere();
        $this->initLimit();
        $this->initOrder();
        $this->initSelect($fields);

        $this->hookInitSelect('select');

        return $this;
    }

    abstract protected function initSelect($fields = null): void;

    public function update(array $data): self
    {
        $this->initUpdate($data);

        $this->setMode(self::MODE_UPDATE);

        return $this;
    }

    abstract protected function initUpdate(array $data): void;

    public function insert(array $data): self
    {
        $this->initInsert($data);

        $this->setMode(self::MODE_INSERT);

        return $this;
    }

    abstract protected function initInsert(array $data): void;

    public function delete($id = null): self
    {
        $this->initWhere();

        if ($id !== null) {
            $this->whereId($id);
        }

        $this->initDelete($id);

        $this->hookInitSelect(__FUNCTION__);

        $this->setMode(self::MODE_DELETE);

        return $this;
    }

    abstract protected function initDelete($id = null): void;

    public function exists(): self
    {
        $this->initWhere();
        $this->initExists();

        $this->hookInitSelect(__FUNCTION__);

        return $this;
    }

    abstract protected function initExists(): void;

    public function count($alias = null): self
    {
        $this->initWhere();
        $this->initCount(...func_get_args());

        $this->hookInitSelect(__FUNCTION__);

        return $this;
    }

    abstract protected function initCount($alias = null): void;

    public function aggregate(string $functionName, $field, string $alias = null, bool $coalesce = false): self
    {
        $this->initWhere();
        $this->initAggregate($functionName, $field, $alias, $coalesce);

        $this->hookInitSelect(__FUNCTION__);

        return $this;
    }

    abstract protected function initAggregate(string $functionName, $field, string $alias = null, bool $coalesce = false): void;

    public function field($fieldName, string $alias = null): self
    {
        $this->initWhere();
        $this->initLimit();
        $this->initOrder();
        $this->initField(...func_get_args());

        if ($this->model->loaded()) {
            $this->whereId($this->model->id);
        }

        $this->hookInitSelect(__FUNCTION__);

        return $this;
    }

    abstract protected function initField($fieldName, string $alias = null): void;

    protected function withMode(): self
    {
        if (!$this->mode) {
            $this->select();
        }

        return $this;
    }

    protected function hookInitSelect($type): void
    {
        if ($this->mode !== self::MODE_SELECT) {
            $this->model->hook(self::HOOK_INIT_SELECT, [$this, $type]);

            $this->setMode(self::MODE_SELECT);
        }
    }

    protected function setMode($mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function where($fieldName, $operator = null, $value = null): self
    {
        $this->scope->addCondition(...func_get_args());

        return $this;
    }

    public function whereId($id)
    {
        if (!$this->model->id_field) {
            throw (new Exception('Unable to find record by "id" when Model::id_field is not defined.'))
                ->addMoreInfo('id', $id);
        }

        $this->where($this->model->getField($this->model->id_field), $id);

        return $this;
    }

    abstract protected function initWhere(): void;

    public function order($field, $desc = null): self
    {
        $this->order[] = [$field, $desc];

        $this->initOrder();

        return $this;
    }

    abstract protected function initOrder(): void;

    public function limit($limit, $offset = 0): self
    {
        $this->limit = [$limit, $offset];

        $this->initLimit();

        return $this;
    }

    abstract protected function initLimit(): void;

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

    public function execute()
    {
        try {
            $this->withMode();

            // backward compatibility
            $this->hookOnModel('HOOK_BEFORE_' . $this->getMode(), [$this]);

//         $this->model->hook(self::HOOK_BEFORE_EXECUTE, [$this]);

            $this->hookOnModel('HOOK_BEFORE_' . $this->getMode(), [$this]);

            $result = $this->doExecute();

            // backward compatibility
            $this->hookOnModel('HOOK_AFTER_' . $this->getMode(), [$this, $result]);

//         $this->model->hook(self::HOOK_AFTER_EXECUTE, [$this, $result]);
        } catch (Exception $e) {
            throw (new Exception('Execution of query failed', 0, $e))
                ->addMoreInfo('query', $this->getDebug())
                ->addMoreInfo('message', $e->getMessage());
        }

        return $result;
    }

    abstract protected function doExecute();

    protected function hookOnModel($name, $args = []): void
    {
        $hookSpotConst = self::class . '::' . $name;
        if (defined($hookSpotConst)) {
            // backward compatibility
//             if ($this->model->hookHasCallbacks(constant($hookSpotConst))) {
//                 'trigger_error'('Hook spot deprecated. Use AbstractQuery::HOOK_BEFORE_EXECUTE or AbstractQuery::HOOK_AFTER_EXECUTE instead', E_USER_DEPRECATED);
//             }
            $this->model->hook(constant($hookSpotConst), $args);
        }
    }

    public function get(): array
    {
        return $this->doGet();
    }

    abstract protected function doGet(): array;

    public function getRow(): ?array
    {
        $this->withMode()->limit(1);

        return $this->doGetRow();
    }

    abstract protected function doGetRow(): ?array;

    public function getOne()
    {
        return $this->doGetOne();
    }

    abstract protected function doGetOne();

    public function getModel(): Model
    {
        return $this->model;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    abstract public function getDebug(): string;
}
