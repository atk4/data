<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Class description?
 */
class Persistence_SQL extends Persistence
{
    /**
     * Connection object.
     *
     * @var \atk4\dsql\Connection
     */
    public $connection;

    /**
     * Default class when adding new field.
     *
     * @var string
     */
    public $_default_class_addField = 'atk4\data\Field_SQL';

    /**
     * Default class when adding hasOne field.
     *
     * @var string
     */
    public $_default_class_hasOne = 'atk4\data\Reference_SQL_One';

    /**
     * Default class when adding hasMany field.
     *
     * @var string
     */
    public $_default_class_hasMany = null; //'atk4\data\Reference_Many';

    /**
     * Default class when adding Expression field.
     *
     * @var string
     */
    public $_default_class_addExpression = 'atk4\data\Field_SQL_Expression';

    /**
     * Default class when adding join.
     *
     * @var string
     */
    public $_default_class_join = 'atk4\data\Join_SQL';

    /**
     * Constructor.
     *
     * @param \atk4\dsql\Connection|string $connection
     * @param string                       $user
     * @param string                       $password
     * @param array                        $args
     */
    public function __construct($connection, $user = null, $password = null, $args = [])
    {
        if ($connection instanceof \atk4\dsql\Connection) {
            $this->connection = $connection;

            return;
        }

        if (is_object($connection)) {
            throw new Exception([
                'You can only use Persistance_SQL with Connection class from atk4\dsql',
                'connection' => $connection,
            ]);
        }

        // attempt to connect.
        $this->connection = \atk4\dsql\Connection::connect(
            $connection,
            $user,
            $password,
            $args
        );
    }

    /**
     * Returns Query instance.
     *
     * @return \atk4\dsql\Query
     */
    public function dsql()
    {
        return $this->connection->dsql();
    }

    /**
     * Atomic executes operations within one begin/end transaction, so if
     * the code inside callback will fail, then all of the transaction
     * will be also rolled back.
     *
     * @param callable $f
     *
     * @return mixed
     */
    public function atomic($f)
    {
        return $this->connection->atomic($f);
    }

    /**
     * Associate model with the data driver.
     *
     * @param Model|string $m        Model which will use this persistence
     * @param array        $defaults Properties
     *
     * @return Model
     */
    public function add($m, $defaults = [])
    {
        // Use our own classes for fields, references and expressions unless
        // $defaults specify them otherwise.
        $defaults = array_merge([
            '_default_class_addField'      => $this->_default_class_addField,
            '_default_class_hasOne'        => $this->_default_class_hasOne,
            '_default_class_hasMany'       => $this->_default_class_hasMany,
            '_default_class_addExpression' => $this->_default_class_addExpression,
            '_default_class_join'          => $this->_default_class_join,
        ], $defaults);

        $m = parent::add($m, $defaults);


        if (!isset($m->table) || (!is_string($m->table) && $m->table !== false)) {
            throw new Exception([
                'Property $table must be specified for a model',
                'model' => $m,
            ]);
        }

        // When we work without table, we can't have any IDs
        if ($m->table === false) {
            $m->getElement('id')->destroy();
            $m->addExpression('id', '1');
        } else {
            // SQL databases use ID of int by default
            $m->getElement('id')->type = 'integer';
        }

        return $m;
    }

    /**
     * Initialize persistence.
     *
     * @param Model $m
     */
    protected function initPersistence(Model $m)
    {
        $m->addMethod('expr', $this);
    }

    /**
     * Creates new Expression object from expression.
     *
     * @param Model  $m
     * @param string $expr
     * @param array  $args
     *
     * @return \atk4\dsql\Expression
     */
    public function expr(Model $m, $expr, $args = [])
    {
        preg_replace_callback(
            '/\[[a-z0-9_]*\]|{[a-z0-9_]*}/',
            function ($matches) use (&$args, $m) {
                $identifier = substr($matches[0], 1, -1);
                if ($identifier && !isset($args[$identifier])) {
                    $args[$identifier] = $m->getElement($identifier);
                }

                return $matches[0];
            },
            $expr
        );

        return $this->connection->expr($expr, $args);
    }

    /**
     * Initializes base query for model $m.
     *
     * @param Model $m
     *
     * @return \atk4\dsql\Query
     */
    public function initQuery($m)
    {
        $d = $m->persistence_data['dsql'] = $this->connection->dsql();

        if ($m->table) {
            if (isset($m->table_alias)) {
                $d->table($m->table, $m->table_alias);
            } else {
                $d->table($m->table);
            }
        }

        return $d;
    }

    /**
     * Adds Field in Query.
     *
     * @param \atk4\dsql\Query $q
     * @param Field            $field
     */
    public function initField($q, $field)
    {
        if ($field->useAlias()) {
            $q->field($field, $field->short_name);
        } else {
            $q->field($field);
        }
    }

    /**
     * Adds model fields in Query.
     *
     * @param Model            $m
     * @param \atk4\dsql\Query $q
     * @param array|null       $fields
     */
    public function initQueryFields($m, $q, $fields = null)
    {
        if ($fields) {

            // Set of fields is strictly defined for purposes of export,
            // so we will ignore even system fields.
            foreach ($fields as $field) {
                $this->initField($q, $m->getElement($field));
            }
        } elseif ($m->only_fields) {
            $added_fields = [];

            // Add requested fields first
            foreach ($m->only_fields as $field) {
                $this->initField($q, $m->getElement($field));
                $added_fields[$field] = true;
            }

            // now add system fields, if they were not added
            foreach ($m->elements as $field => $f_object) {
                if ($f_object instanceof Field_SQL && $f_object->system && !isset($added_fields[$field])) {
                    $this->initField($q, $f_object);
                }
            }
        } else {
            foreach ($m->elements as $field => $f_object) {
                if ($f_object instanceof Field_SQL) {
                    if ($f_object->never_persist) {
                        continue;
                    }
                    $this->initField($q, $f_object);
                }
            }
        }
    }

    /**
     * Will set limit defined inside $m onto query $q.
     *
     * @param Model            $m
     * @param \atk4\dsql\Query $q
     */
    protected function setLimitOrder($m, $q)
    {
        if ($m->limit && ($m->limit[0] || $m->limit[1])) {
            if ($m->limit[0] === null) {
                // This is max number which is allowed in MySQL server.
                // But be aware, that PDO will downgrade this number even lower probably because
                // in LIMIT it expects numeric value and converts string (we set float values as PDO_PARAM_STR)
                // back to PDO_PARAM_INT which is goes back to max int value specific server can have.
                // On my Win10,64-bit it is 2147483647, on Travis server 9223372036854775807 etc.
                $m->limit[0] = '18446744073709551615';
            }
            $q->limit($m->limit[0], $m->limit[1]);
        }

        if ($m->order) {
            foreach ($m->order as $o) {
                $q->order($m->getElement($o[0]), $o[1]);
            }
        }
    }

    /**
     * Will apply conditions defined inside $m onto query $q.
     *
     * @param Model            $m
     * @param \atk4\dsql\Query $q
     *
     * @return \atk4\dsql\Query
     */
    public function initQueryConditions($m, $q)
    {
        if (!isset($m->conditions)) {
            // no conditions are set in the model
            return $q;
        }

        foreach ($m->conditions as $cond) {

            // Options here are:
            // count($cond) == 1, we will pass the only
            // parameter inside where()

            if (count($cond) == 1) {
                $q->where($cond[0]);
                continue;
            }

            if (is_string($cond[0])) {
                $cond[0] = $m->getElement($cond[0]);
            }

            if (count($cond) == 2) {
                if ($cond[0] instanceof Field) {
                    $cond[1] = $this->typecastSaveField($cond[0], $cond[1]);
                }
                $q->where($cond[0], $cond[1]);
            } else {
                if ($cond[0] instanceof Field) {
                    $cond[2] = $this->typecastSaveField($cond[0], $cond[2]);
                }
                $q->where($cond[0], $cond[1], $cond[2]);
            }
        }

        return $q;
    }

    /**
     * Will convert one row of data from Persistence-specific
     * types to PHP native types.
     *
     * @param Model $m
     * @param array $row
     *
     * @return array
     */
    public function typecastLoadRow($m, $row)
    {
        if (!$row) {
            return $row;
        }
        foreach ($row as $key => &$value) {
            if ($value === null) {
                continue;
            }

            if ($f = $m->hasElement($key)) {
                $value = $this->typecastLoadField($f, $value);
            }
        }

        return $row;
    }

    /**
     * Will convert one row of data from native PHP types into
     * persistence types. This will also take care of the "actual"
     * field keys. Example:.
     *
     * In:
     *  [
     *    'name'=>' John Smith',
     *    'age'=>30,
     *    'password'=>'abc',
     *    'is_married'=>true,
     *  ]
     *
     *  Out:
     *   [
     *     'first_name'=>'John Smith',
     *     'age'=>30,
     *     'is_married'=>1
     *   ]
     *
     * @param Model $m
     * @param array $row
     *
     * @return array
     */
    public function typecastSaveRow($m, $row)
    {
        if (!$row) {
            return $row;
        }
        $result = [];
        foreach ($row as $key => $value) {

            // Look up field object
            $f = $m->hasElement($key);

            // We have no knowledge of the field, it wasn't defined, so
            // we will leave it as-is.
            if (!$f) {
                $result[$field] = $value;
                continue;
            }

            // Figure out the name of the destination field
            $field = $f->actual ?: $key;

            if (
                $value === null && $f->mandatory
            ) {
                throw new Exception(['Mandatory field value cannot be null', 'field' => $key]);
            }

            // Expression and null cannot be converted.
            if (
                $value instanceof \atk4\dsql\Expression ||
                $value instanceof \atk4\dsql\Expressionable ||
                $value === null
            ) {
                $result[$field] = $value;
                continue;
            }

            $result[$field] = $this->typecastSaveField($f, $value);
        }

        return $result;
    }

    /**
     * Cast specific field value from the way how it's stored inside
     * persistence to a PHP format.
     *
     * @param Field $f
     * @param mixed $value
     *
     * @return mixed
     */
    public function typecastLoadField(Field $f, $value)
    {
        if ($callback = $f->loadCallback) {
            return $callback($value, $f, $this);
        }

        if ($value === null) {
            return;
        }

        // only string type fields can use empty string as legit value, for all
        // other field types empty value is the same as no-value, nothing or null
        if ($f->type != 'string' && $value === '') {
            return;
        }

        switch ($f->type) {
        case 'boolean':

            if (isset($f->enum) && is_array($f->enum)) {
                if (isset($f->enum[0]) && $value == $f->enum[0]) {
                    $value = false;
                } elseif (isset($f->enum[1]) && $value == $f->enum[1]) {
                    $value = true;
                } else {
                    $value = null;
                }
            } else {
                $value = (bool) $value;
            }

            break;
        case 'money':
            $value = round($value, 4);

            return $value;
        case 'date':
        case 'datetime':
        case 'time':
            // Can use DateTime, Carbon or anything else
            $class = isset($f->dateTimeClass) ? $f->dateTimeClass : 'DateTime';

            if (is_numeric($value)) {
                $value = new $class('@'.$value);
            } elseif (is_string($value)) {
                $value = new $class($value, new \DateTimeZone('UTC'));
            }
            break;
        case 'integer':
            $value = (int) $value;
            break;
        case 'float':
            $value = (float) $value;
            break;
        case 'struct':
            $value = json_decode($value, true) ?: [];
            break;
        }

        return $value;
    }

    /**
     * Prepare value of a specific field by converting it to
     * persistence - friendly format.
     *
     * @param Field $f
     * @param mixed $value
     *
     * @return mixed
     */
    public function typecastSaveField(Field $f, $value)
    {

        // Support for callback: (28, ['age', [persistence_object]])
        if ($callback = $f->saveCallback) {
            return $callback($value, $f, $this);
        }

        if ($value === null) {
            return;
        }

        // only string type fields can use empty string as legit value, for all
        // other field types empty value is the same as no-value, nothing or null
        if ($f->type != 'string' && $value === '') {
            return;
        }

        // Manually handle remaining types
        switch ($f->type) {
        case 'boolean':
            if (isset($f->enum) && is_array($f->enum) && isset($f->enum[0]) && isset($f->enum[1])) {
                $value = $value ? $f->enum[1] : $f->enum[0];
            } else {
                $value = (int) $value;
            }
            break;
        case 'date':
        case 'datetime':
        case 'time':
            // Datetime only - change to UTC
            $class = isset($f->dateTimeClass) ? $f->dateTimeClass : 'DateTime';

            $format = ['date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s', 'time' => 'H:i:s'];

            if ($value instanceof $class && $f->type == 'datetime') {
                $value->setTimezone(new \DateTimeZone('UTC'));
            }

            // Format for SQL
            $value = $value->format(isset($f->dateFormat) ? $f->dateFormat : $format[$f->type]);
            break;
        case 'struct':
            $value = json_encode($value);
            break;
        }

        return $value;
    }

    /**
     * Executing $model->action('update') will call this method.
     *
     * @param Model  $m
     * @param string $type
     * @param array  $args
     *
     * @return \atk4\dsql\Query
     */
    public function action($m, $type, $args = [])
    {
        if (!is_array($args)) {
            throw new Exception([
                '$args must be an array',
                'args' => $args,
            ]);
        }

        $q = $this->initQuery($m);
        switch ($type) {
            case 'insert':
                return $q->mode('insert');
                // cannot apply conditions now

            case 'update':
                $q->mode('update');
                break;

            case 'delete':
                $q->mode('delete');
                $this->initQueryConditions($m, $q);
                $m->hook('initSelectQuery', [$q, $type]);

                return $q;

            case 'select':
                $this->initQueryFields($m, $q, isset($args[0]) ? $args[0] : null);
                break;

            case 'count':
                $this->initQueryConditions($m, $q);
                $m->hook('initSelectQuery', [$q]);
                $q->reset('field')->field('count(*)');

                return $q;

            case 'field':
                if (!isset($args[0])) {
                    throw new Exception([
                        'This action requires one argument with field name',
                        'action' => $type,
                    ]);
                }

                $field = is_string($args[0]) ? $m->getElement($args[0]) : $args[0];
                $m->hook('initSelectQuery', [$q, $type]);
                $q->reset('field')->field($field);
                $this->initQueryConditions($m, $q);
                $this->setLimitOrder($m, $q);

                return $q;

            case 'fx':
                if (!isset($args[0], $args[1])) {
                    throw new Exception([
                        'fx action needs 2 arguments, eg: ["sum", "amount"]',
                        'action' => $type,
                    ]);
                }

                $fx = $args[0];
                $field = is_string($args[1]) ? $m->getElement($args[1]) : $args[1];
                $this->initQueryConditions($m, $q);
                $m->hook('initSelectQuery', [$q, $type]);
                $q->reset('field')->field($q->expr("$fx([])", [$field]));

                return $q;

            default:
                throw new Exception([
                    'Unsupported action mode',
                    'type' => $type,
                ]);
        }

        $this->initQueryConditions($m, $q);
        $this->setLimitOrder($m, $q);
        $m->hook('initSelectQuery', [$q, $type]);

        return $q;
    }

    /**
     * Tries to load data record, but will not fail if record can't be loaded.
     *
     * @param Model $m
     * @param mixed $id
     *
     * @return array
     */
    public function tryLoad(Model $m, $id)
    {
        $load = $this->action($m, 'select');
        $load->where($m->getElement($m->id_field), $id);
        $load->limit(1);

        // execute action
        try {
            $data = $this->typecastLoadRow($m, $load->getRow());
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to load due to query error',
                'query'      => $load->getDebugQuery(false),
                'model'      => $m,
                'conditions' => $m->conditions,
            ], null, $e);
        }

        if (!$data) {
            return;
        }

        if (isset($data[$m->id_field])) {
            $m->id = $data[$m->id_field];
        } else {
            throw new Exception([
                'ID of the record is unavailable. Read-only mode is not supported',
                'model' => $m,
                'id'    => $id,
                'data'  => $data,
            ]);
        }

        return $data;
    }

    /**
     * Loads a record from model and returns a associative array.
     *
     * @param Model $m
     * @param mixed $id
     *
     * @return array
     */
    public function load(Model $m, $id)
    {
        $data = $this->tryLoad($m, $id);

        if (!$data) {
            throw new Exception([
                'Record was not found',
                'model'      => $m,
                'id'         => $id,
                'conditions' => $m->conditions,
            ], 404);
        }

        return $data;
    }

    /**
     * Tries to load any one record.
     *
     * @param Model $m
     *
     * @return array
     */
    public function tryLoadAny(Model $m)
    {
        $load = $this->action($m, 'select');
        $load->limit(1);

        // execute action
        try {
            $data = $this->typecastLoadRow($m, $load->getRow());
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to load due to query error',
                'query'      => $load->getDebugQuery(false),
                'model'      => $m,
                'conditions' => $m->conditions,
            ], null, $e);
        }


        if (!$data) {
            return;
        }

        if (isset($data[$m->id_field])) {
            $m->id = $data[$m->id_field];
        } else {
            throw new Exception([
                'ID of the record is unavailable. Read-only mode is not supported',
                'model' => $m,
                'data'  => $data,
            ]);
        }

        return $data;
    }

    /**
     * Loads any one record.
     *
     * @param Model $m
     *
     * @return array
     */
    public function loadAny(Model $m)
    {
        $data = $this->tryLoadAny($m);

        if (!$data) {
            throw new Exception([
                'No matching records were found',
                'model'      => $m,
                'conditions' => $m->conditions,
            ], 404);
        }

        return $data;
    }

    /**
     * Inserts record in database and returns new record ID.
     *
     * @param Model $m
     * @param array $data
     *
     * @return mixed
     */
    public function insert(Model $m, $data)
    {
        $insert = $this->action($m, 'insert');
        $insert->set($this->typecastSaveRow($m, $data));

        $st = null;
        try {
            $m->hook('beforeInsertQuery', [$insert]);
            $st = $insert->execute();
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to execute insert query',
                'query'      => $insert->getDebugQuery(false),
                'model'      => $m,
                'conditions' => $m->conditions,
            ], null, $e);
        }

        $m->hook('afterInsertQuery', [$insert, $st]);

        return $insert->connection->lastInsertID();
    }

    /**
     * Export all DataSet.
     *
     * @param Model      $m
     * @param array|null $fields
     *
     * @return array
     */
    public function export(Model $m, $fields = null)
    {
        $export = $this->action($m, 'select', [$fields]);

        return array_map(function ($r) use ($m) {
            return $this->typecastLoadRow($m, $r);
        }, $export->get());
    }

    /**
     * Prepare iterator.
     *
     * @param Model $m
     *
     * @return \PDOStatement
     */
    public function prepareIterator(Model $m)
    {
        try {
            $export = $this->action($m, 'select');

            return $export->execute();
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to execute iteration query',
                'query'      => $export->getDebugQuery(false),
                'model'      => $m,
                'conditions' => $m->conditions,
            ], null, $e);
        }
    }

    /**
     * Updates record in database.
     *
     * @param Model $m
     * @param mixed $id
     * @param array $data
     */
    public function update(Model $m, $id, $data)
    {
        $update = $this->initQuery($m);
        $update->mode('update');

        $data = $this->typecastSaveRow($m, $data);

        // only apply fields that has been modified
        $cnt = 0;
        foreach ($data as $field => $value) {
            $f = $m->getElement($field);
            $update->set($f->actual ?: $f->short_name, $value);
            $cnt++;
        }
        $update->where($m->getElement($m->id_field), $id);


        $st = null;

        try {
            $m->hook('beforeUpdateQuery', [$update]);
            if ($cnt) {
                $st = $update->execute();
            }
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to update due to query error',
                'query'      => $update->getDebugQuery(false),
                'model'      => $m,
                'conditions' => $m->conditions,
            ], null, $e);
        }

        if ($m->id_field && isset($data[$m->id_field]) && $m->dirty[$m->id_field]) {
            // ID was changed
            $m->id = $data[$m->id_field];
        }

        $m->hook('afterUpdateQuery', [$update, $st]);

        // if any rows were updated in database, and we had expressions, reload
        if ($m->reload_after_save === true && (!$st || $st->rowCount())) {
            $d = $m->dirty;
            $m->reload();
            $m->_dirty_after_reload = $m->dirty;
            $m->dirty = $d;
        }
    }

    /**
     * Deletes record from database.
     *
     * @param Model $m
     * @param mixed $id
     */
    public function delete(Model $m, $id)
    {
        $delete = $this->initQuery($m);
        $delete->mode('delete');
        $delete->where($m->id_field, $id);
        $m->hook('beforeDeleteQuery', [$delete]);

        try {
            $delete->execute();
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to delete due to query error',
                'query'      => $delete->getDebugQuery(false),
                'model'      => $m,
                'conditions' => $m->conditions,
            ], null, $e);
        }
    }
}
