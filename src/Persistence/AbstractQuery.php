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

    /** @var Model\Scope */
    protected $scope;

    /** @var array */
    protected $order = [];

    /** @var array */
    protected $limit = [];

    /** @var string */
    protected $mode;

    public function __construct(Model $model)
    {
        $this->model = clone $model;

        $this->scope = $this->model->scope();

        $this->order = $model->order;

        $this->limit = $model->limit;
    }

    /**
     * Find and return data from record with $id or NULL if none found.
     */
    public function find($id): ?array
    {
        return $this->whereId($id)->getRow();
    }

    /**
     * Setup query as selecting list of $fields or all if $field = NULL.
     *
     * @param array|false|null $fields
     */
    public function select($fields = null): self
    {
        $this->initWhere();
        $this->initLimit();
        $this->initOrder();
        $this->initSelect($fields);

        $this->hookInitSelect('select');

        return $this;
    }

    /**
     * Initiate the select operation in the child class.
     *
     * @param array|false|null $fields
     */
    abstract protected function initSelect($fields = null): void;

    /**
     * Setup query as updating records in the AbstractQuery::$scope using $data.
     */
    public function update(array $data): self
    {
        $this->initUpdate($data);

        $this->setMode(self::MODE_UPDATE);

        return $this;
    }

    /**
     * Initiate the update operation in the child class.
     */
    abstract protected function initUpdate(array $data): void;

    /**
     * Setup query as inserting a record using $data.
     */
    public function insert(array $data): self
    {
        $this->initInsert($data);

        $this->setMode(self::MODE_INSERT);

        return $this;
    }

    /**
     * Initiate the insert operation in the child class.
     */
    abstract protected function initInsert(array $data): void;

    /**
     * Setup query as deleting record(s) within the AbstractQuery::$scope.
     * If $id argument provided only record with $id will be deleted if within the scope.
     *
     * @param int|string $id
     */
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

    /**
     * Initiate the delete operation in the child class.
     */
    abstract protected function initDelete($id = null): void;

    /**
     * Setup query as exists within the AbstractQuery::$scope.
     */
    public function exists(): self
    {
        $this->initWhere();
        $this->initExists();

        $this->hookInitSelect(__FUNCTION__);

        return $this;
    }

    /**
     * Initiate the exists operation in the child class.
     */
    abstract protected function initExists(): void;

    /**
     * Setup query as counting of records within the AbstractQuery::$scope.
     */
    public function count($alias = null): self
    {
        $this->initWhere();
        $this->initCount(...func_get_args());

        $this->hookInitSelect(__FUNCTION__);

        return $this;
    }

    /**
     * Initiate the count operation in the child class.
     */
    abstract protected function initCount($alias = null): void;

    /**
     * Setup query as aggregate function result of records within the AbstractQuery::$scope.
     */
    public function aggregate(string $functionName, $field, string $alias = null, bool $coalesce = false): self
    {
        $this->initWhere();
        $this->initAggregate($functionName, $field, $alias, $coalesce);

        $this->hookInitSelect(__FUNCTION__);

        return $this;
    }

    /**
     * Initiate the aggregate operation in the child class.
     */
    abstract protected function initAggregate(string $functionName, $field, string $alias = null, bool $coalesce = false): void;

    /**
     * Setup query as selecting a field value from records within the AbstractQuery::$scope.
     */
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

    /**
     * Initiate the field operation in the child class.
     */
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

    /**
     * Add condition to the query scope (leaves model scope intact).
     */
    public function where($fieldName, $operator = null, $value = null): self
    {
        $this->scope->addCondition(...func_get_args());

        return $this;
    }

    /**
     * Limit scope to only records with $id (leaves model scope intact).
     */
    public function whereId($id)
    {
        if (!$this->model->id_field) {
            throw (new Exception('Unable to find record by "id" when Model::id_field is not defined.'))
                ->addMoreInfo('id', $id);
        }

        $this->where($this->model->getField($this->model->id_field), $id);

        return $this;
    }

    /**
     * Initiate the application of scope conditions in the underlying query engine.
     */
    abstract protected function initWhere(): void;

    /**
     * Set the order required from query result (leaves model order intact).
     */
    public function order($field, $desc = null): self
    {
        $this->order[] = [$field, $desc];

        $this->initOrder();

        return $this;
    }

    /**
     * Initiate the application of order in the underlying query engine.
     */
    abstract protected function initOrder(): void;

    /**
     * Set the limit required from query result (leaves model order intact).
     */
    public function limit($limit, $offset = 0): self
    {
        $this->limit = [$limit, $offset];

        $this->initLimit();

        return $this;
    }

    /**
     * Initiate the application of limit in the underlying query engine.
     */
    abstract protected function initLimit(): void;

    /**
     * Converts limit array to arguments [$limit, $offset].
     */
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

    /**
     * Executes the query and returns the result.
     */
    public function execute()
    {
        return $this->executeQueryWithDebug(function () {
            $this->withMode();

            // backward compatibility
            $this->hookOnModel('HOOK_BEFORE_' . $this->getMode(), [$this]);

//          $this->model->hook(self::HOOK_BEFORE_EXECUTE, [$this]);

            $this->hookOnModel('HOOK_BEFORE_' . $this->getMode(), [$this]);

            $result = $this->doExecute();

            // backward compatibility
            $this->hookOnModel('HOOK_AFTER_' . $this->getMode(), [$this, $result]);

//          $this->model->hook(self::HOOK_AFTER_EXECUTE, [$this, $result]);

            return $result;
        });
    }

    /**
     * Actual routine for query execution defined in child class.
     */
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

    /**
     * Get array of records matching the query.
     */
    public function get(): array
    {
        return $this->executeQueryWithDebug(function () {
            return $this->doGet();
        });
    }

    /**
     * Actual routine for query get execution defined in child class.
     */
    abstract protected function doGet(): array;

    /**
     * Get one row from the records matching the query.
     */
    public function getRow(): ?array
    {
        return $this->executeQueryWithDebug(function () {
            $this->withMode()->limit(1);

            return $this->doGetRow();
        });
    }

    /**
     * Actual routine for query get row execution defined in child class.
     */
    abstract protected function doGetRow(): ?array;

    /**
     * Get value from the first field of the first record in the query results.
     */
    public function getOne()
    {
        return $this->executeQueryWithDebug(function () {
            return $this->doGetOne();
        });
    }

    /**
     * Actual routine for query get one execution defined in child class.
     */
    abstract protected function doGetOne();

    protected function executeQueryWithDebug(\Closure $fx)
    {
        try {
            return $fx();
        } catch (Exception $e) {
            throw (new Exception('Execution of query failed', 0, $e))
                ->addMoreInfo('message', $e->getMessage())
                ->addMoreInfo('query', $this->getDebug());
        }
    }

    public function getIterator(): iterable
    {
        return $this->execute();
    }

    /**
     * Return the model the query runs on.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Return the mode the query is setup for.
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Return the mode the query debug array with necessary details.
     */
    public function getDebug(): array
    {
        return [
            'mode' => $this->mode,
            'model' => $this->model,
            'scope' => $this->scope->toWords($this->model),
            'order' => $this->order,
            'limit' => $this->limit,
        ];
    }
}
