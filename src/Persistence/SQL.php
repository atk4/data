<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Persistence;

use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\Field_SQL_Expression;
use atk4\data\Model;
use atk4\data\Persistence;
use atk4\dsql\Connection;
use atk4\dsql\Expression;
use atk4\dsql\Query;

/**
 * Persistence\SQL class.
 */
class SQL extends Persistence
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
    public $_default_seed_addField = ['\atk4\data\Field_SQL'];

    /**
     * Default class when adding hasOne field.
     *
     * @var string
     */
    public $_default_seed_hasOne = ['\atk4\data\Reference\HasOne_SQL'];

    /**
     * Default class when adding hasMany field.
     *
     * @var string
     */
    public $_default_seed_hasMany = null; //'atk4\data\Reference\HasMany';

    /**
     * Default class when adding Expression field.
     *
     * @var string
     */
    public $_default_seed_addExpression = ['\atk4\data\Field_SQL_Expression'];

    /**
     * Default class when adding join.
     *
     * @var string
     */
    public $_default_seed_join = ['\atk4\data\Join\SQL'];

    /**
     * Constructor.
     *
     * @param Connection|string $connection
     * @param string            $user
     * @param string            $password
     * @param array             $args
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
     * Disconnect from database explicitly.
     */
    public function disconnect()
    {
        parent::disconnect();

        unset($this->connection);
    }

    /**
     * Returns Query instance.
     *
     * @return Query
     */
    public function dsql(): Query
    {
        return $this->connection->dsql();
    }

    /**
     * Atomic executes operations within one begin/end transaction, so if
     * the code inside callback will fail, then all of the transaction
     * will be also rolled back.
     *
     * @param callable $fx
     *
     * @return mixed
     */
    public function atomic($fx)
    {
        return $this->connection->atomic($fx);
    }

    /**
     * Associate model with the data driver.
     *
     * @param Model|string $model    Model which will use this persistence
     * @param array        $defaults Properties
     *
     * @return Model
     */
    public function add($model, $defaults = []): Model
    {
        // Use our own classes for fields, references and expressions unless
        // $defaults specify them otherwise.
        $defaults = array_merge([
            '_default_seed_addField'      => $this->_default_seed_addField,
            '_default_seed_hasOne'        => $this->_default_seed_hasOne,
            '_default_seed_hasMany'       => $this->_default_seed_hasMany,
            '_default_seed_addExpression' => $this->_default_seed_addExpression,
            '_default_seed_join'          => $this->_default_seed_join,
        ], $defaults);

        $model = parent::add($model, $defaults);

        if (!isset($model->table) || (!is_string($model->table) && $model->table !== false)) {
            throw new Exception([
                'Property $table must be specified for a model',
                'model' => $model,
            ]);
        }

        // When we work without table, we can't have any IDs
        if ($model->table === false) {
            $model->removeField($model->id_field);
            $model->addExpression($model->id_field, '1');
            //} else {
            // SQL databases use ID of int by default
            //$m->getField($m->id_field)->type = 'integer';
        }

        // Sequence support
        if ($model->sequence && $id_field = $model->hasField($model->id_field)) {
            $id_field->default = $this->dsql()->mode('seq_nextval')->sequence($model->sequence);
        }

        return $model;
    }

    /**
     * Initialize persistence.
     *
     * @param Model $model
     */
    protected function initPersistence(Model $model)
    {
        $model->addMethod('expr', $this);
        $model->addMethod('dsql', $this);
        $model->addMethod('exprNow', $this);
    }

    /**
     * Creates new Expression object from expression string.
     *
     * @param Model $model
     * @param mixed $expr
     * @param array $args
     *
     * @return Expression
     */
    public function expr(Model $model, $expr, $args = []): Expression
    {
        if (!is_string($expr)) {
            return $this->connection->expr($expr, $args);
        }
        preg_replace_callback(
            '/\[[a-z0-9_]*\]|{[a-z0-9_]*}/i',
            function ($matches) use (&$args, $model) {
                $identifier = substr($matches[0], 1, -1);
                if ($identifier && !isset($args[$identifier])) {
                    $args[$identifier] = $model->getField($identifier);
                }

                return $matches[0];
            },
            $expr
        );

        return $this->connection->expr($expr, $args);
    }

    /**
     * Creates new Query object with current_timestamp(precision) expression.
     *
     * @param int $precision
     *
     * @return Query
     */
    public function exprNow($precision = null)
    {
        return $this->connection->dsql()->exprNow($precision);
    }

    /**
     * Initializes base query for model $m.
     *
     * @param Model $model
     *
     * @return Query
     */
    public function initQuery(Model $model): Query
    {
        $query = $model->persistence_data['dsql'] = $this->dsql();

        if ($model->table) {
            $query->table($model->table, $model->table_alias ?? null);
        }

        return $query;
    }

    /**
     * Adds Field in Query.
     *
     * @param Query $query
     * @param Field $field
     */
    public function initField(Query $query, Field $field)
    {
        $query->field($field, $field->useAlias() ? $field->short_name : null);
    }

    /**
     * Adds model fields in Query.
     *
     * @param Model            $model
     * @param \atk4\dsql\Query $query
     * @param array|null|false $fields
     */
    public function initQueryFields(Model $model, $query, $fields = null)
    {
        // do nothing on purpose
        if ($fields === false) {
            return;
        }

        // init fields
        if (is_array($fields)) {

            // Set of fields is strictly defined for purposes of export,
            // so we will ignore even system fields.
            foreach ($fields as $field) {
                $this->initField($query, $model->getField($field));
            }
        } elseif ($model->only_fields) {
            $added_fields = [];

            // Add requested fields first
            foreach ($model->only_fields as $field) {
                $f_object = $model->getField($field);
                if ($f_object->never_persist) {
                    continue;
                }
                $this->initField($query, $f_object);
                $added_fields[$field] = true;
            }

            // now add system fields, if they were not added
            foreach ($model->getFields() as $field => $f_object) {
                if ($f_object->never_persist) {
                    continue;
                }
                if ($f_object->system && !isset($added_fields[$field])) {
                    $this->initField($query, $f_object);
                }
            }
        } else {
            foreach ($model->getFields() as $field => $f_object) {
                if ($f_object->never_persist) {
                    continue;
                }
                $this->initField($query, $f_object);
            }
        }
    }

    /**
     * Will set limit defined inside $m onto query $q.
     *
     * @param Model $model
     * @param Query $query
     */
    protected function setLimitOrder(Model $model, Query $query)
    {
        // set limit
        if ($model->limit && ($model->limit[0] || $model->limit[1])) {
            if ($model->limit[0] === null) {
                // This is max number which is allowed in MySQL server.
                // But be aware, that PDO will downgrade this number even lower probably because
                // in LIMIT it expects numeric value and converts string (we set float values as PDO_PARAM_STR)
                // back to PDO_PARAM_INT which is goes back to max int value specific server can have.
                // On my Win10,64-bit it is 2147483647, on Travis server 9223372036854775807 etc.
                $model->limit[0] = '18446744073709551615';
            }
            $query->limit($model->limit[0], $model->limit[1]);
        }

        // set order
        if ($model->order) {
            foreach ($model->order as $o) {
                if ($o[0] instanceof Expression) {
                    $query->order($o[0], $o[1]);
                } elseif (is_string($o[0])) {
                    $query->order($model->getField($o[0]), $o[1]);
                } else {
                    throw new Exception(['Unsupported order parameter', 'model' => $model, 'field' => $o[0]]);
                }
            }
        }
    }

    /**
     * Will apply conditions defined inside $m onto query $q.
     *
     * @param Model $model
     * @param Query $query
     *
     * @return Query
     */
    public function initQueryConditions(Model $model, Query $query): Query
    {
        if (!isset($model->conditions)) {
            // no conditions are set in the model
            return $query;
        }

        foreach ($model->conditions as $cond) {

            // Options here are:
            // count($cond) == 1, we will pass the only
            // parameter inside where()

            if (count($cond) == 1) {

                // OR conditions
                if (is_array($cond[0])) {
                    foreach ($cond[0] as &$row) {
                        if (is_string($row[0])) {
                            $row[0] = $model->getField($row[0]);
                        }

                        if ($row[0] instanceof Field) {
                            $valueKey = count($row) == 2 ? 1 : 2;

                            $row[$valueKey] = $this->typecastSaveField($row[0], $row[$valueKey]);
                        }
                    }
                }

                $query->where($cond[0]);
                continue;
            }

            if (is_string($cond[0])) {
                $cond[0] = $model->getField($cond[0]);
            }

            if (count($cond) == 2) {
                if ($cond[0] instanceof Field) {
                    $cond[1] = $this->typecastSaveField($cond[0], $cond[1]);
                }
                $query->where($cond[0], $cond[1]);
            } else {
                if ($cond[0] instanceof Field) {
                    $cond[2] = $this->typecastSaveField($cond[0], $cond[2]);
                }
                $query->where($cond[0], $cond[1], $cond[2]);
            }
        }

        return $query;
    }

    /**
     * This is the actual field typecasting, which you can override in your
     * persistence to implement necessary typecasting.
     *
     * @param Field $f
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastSaveField(Field $field, $value)
    {
        // work only on copied value not real one !!!
        $v = is_object($value) ? clone $value : $value;

        switch ($field->type) {
        case 'boolean':
            // if enum is not set, then simply cast value to integer
            if (!isset($field->enum) || !$field->enum) {
                $v = (int) $v;
                break;
            }

            // if enum is set, first lets see if it matches one of those precisely
            if ($v === $field->enum[1]) {
                $v = true;
            } elseif ($v === $field->enum[0]) {
                $v = false;
            }

            // finally, convert into appropriate value
            $v = $v ? $field->enum[1] : $field->enum[0];
            break;
        case 'date':
        case 'datetime':
        case 'time':
            $dt_class = $field->dateTimeClass ?? 'DateTime';
            $tz_class = $field->dateTimeZoneClass ?? 'DateTimeZone';

            if ($v instanceof $dt_class || $v instanceof \DateTimeInterface) {
                $format = ['date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s.u', 'time' => 'H:i:s.u'];
                $format = $field->persistence['format'] ?? $format[$field->type];

                // datetime only - set to persisting timezone
                if ($field->type == 'datetime' && isset($field->persistence['timezone'])) {
                    $v->setTimezone(new $tz_class($field->persistence['timezone']));
                }
                $v = $v->format($format);
            }
            break;
        case 'array':
        case 'object':
            // don't encode if we already use some kind of serialization
            $v = $field->serialize ? $v : $this->jsonEncode($field, $v);
            break;
        }

        return $v;
    }

    /**
     * This is the actual field typecasting, which you can override in your
     * persistence to implement necessary typecasting.
     *
     * @param Field $field
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastLoadField(Field $field, $value)
    {
        // LOB fields return resource stream
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        // work only on copied value not real one !!!
        $v = is_object($value) ? clone $value : $value;

        switch ($field->type) {
            case 'string':
            case 'text':
                // do nothing - it's ok as it is
                break;
            case 'integer':
                $v = (int) $v;
                break;
            case 'float':
                $v = (float) $v;
                break;
            case 'money':
                $v = round($v, 4);
                break;
            case 'boolean':
                if (isset($field->enum) && is_array($field->enum)) {
                    if (isset($field->enum[0]) && $v == $field->enum[0]) {
                        $v = false;
                    } elseif (isset($field->enum[1]) && $v == $field->enum[1]) {
                        $v = true;
                    } else {
                        $v = null;
                    }
                } elseif ($v === '') {
                    $v = null;
                } else {
                    $v = (bool) $v;
                }
                break;
            case 'date':
            case 'datetime':
            case 'time':
                $dt_class = isset($field->dateTimeClass) ? $field->dateTimeClass : 'DateTime';
                $tz_class = isset($field->dateTimeZoneClass) ? $field->dateTimeZoneClass : 'DateTimeZone';

                if (is_numeric($v)) {
                    $v = new $dt_class('@'.$v);
                } elseif (is_string($v)) {
                    if ($field->persistence['format'] ?? null) {
                        $format = $field->persistence['format'];
                    } else {
                        // ! symbol in date format is essential here to remove time part of DateTime - don't remove, this is not a bug
                        $formatMap = ['date' => '+!Y-m-d', 'datetime' => '+!Y-m-d H:i:s', 'time' => '+!H:i:s'];

                        $format = $formatMap[$field->type];

                        if (strpos($v, '.') !== false) { // time possibly with microseconds, otherwise invalid format
                            $format = preg_replace('~(?<=H:i:s)(?![. ]*u)~', '.u', $format);
                        }
                    }

                    // datetime only - set from persisting timezone
                    if ($field->type == 'datetime' && isset($field->persistence['timezone'])) {
                        $v = $dt_class::createFromFormat($format, $v, new $tz_class($field->persistence['timezone']));
                        if ($v !== false) {
                            $v->setTimezone(new $tz_class(date_default_timezone_get()));
                        }
                    } else {
                        $v = $dt_class::createFromFormat($format, $v);
                    }

                    if ($v === false) {
                        throw new Exception(['Incorrectly formatted date/time', 'format' => $format, 'value' => $value, 'field' => $field]);
                    }

                    // need to cast here because DateTime::createFromFormat returns DateTime object not $dt_class
                    // this is what Carbon::instance(DateTime $dt) method does for example
                    if ($dt_class != 'DateTime') {
                        $v = new $dt_class($v->format('Y-m-d H:i:s.u'), $v->getTimeZone());
                    }
                }
                break;
            case 'array':
                // don't decode if we already use some kind of serialization
                $v = $field->serialize ? $v : $this->jsonDecode($field, $v, true);
                break;
            case 'object':
                // don't decode if we already use some kind of serialization
                $v = $field->serialize ? $v : $this->jsonDecode($field, $v, false);
                break;
        }

        return $v;
    }

    /**
     * Executing $model->action('update') will call this method.
     *
     * @param Model  $model
     * @param string $type
     * @param array  $args
     *
     * @return \atk4\dsql\Query
     */
    public function action(Model $model, $type, $args = [])
    {
        if (!is_array($args)) {
            throw new Exception([
                '$args must be an array',
                'args' => $args,
            ]);
        }

        $query = $this->initQuery($model);
        switch ($type) {
            case 'insert':
                return $query->mode('insert');
                // cannot apply conditions now

            case 'update':
                $query->mode('update');
                break;

            case 'delete':
                $query->mode('delete');
                $this->initQueryConditions($model, $query);
                $model->hook('initSelectQuery', [$query, $type]);

                return $query;

            case 'select':
                $this->initQueryFields($model, $query, isset($args[0]) ? $args[0] : null);
                break;

            case 'count':
                $this->initQueryConditions($model, $query);
                $model->hook('initSelectQuery', [$query]);
                if (isset($args['alias'])) {
                    $query->reset('field')->field('count(*)', $args['alias']);
                } else {
                    $query->reset('field')->field('count(*)');
                }

                return $query;

            case 'field':
                if (!isset($args[0])) {
                    throw new Exception([
                        'This action requires one argument with field name',
                        'action' => $type,
                    ]);
                }

                $field = is_string($args[0]) ? $model->getField($args[0]) : $args[0];
                $model->hook('initSelectQuery', [$query, $type]);
                if (isset($args['alias'])) {
                    $query->reset('field')->field($field, $args['alias']);
                } elseif ($field instanceof Field_SQL_Expression) {
                    $query->reset('field')->field($field, $field->short_name);
                } else {
                    $query->reset('field')->field($field);
                }
                $this->initQueryConditions($model, $query);
                $this->setLimitOrder($model, $query);

                return $query;

            case 'fx':
            case 'fx0':
                if (!isset($args[0], $args[1])) {
                    throw new Exception([
                        'fx action needs 2 arguments, eg: ["sum", "amount"]',
                        'action' => $type,
                    ]);
                }

                $fx = $args[0];
                $field = is_string($args[1]) ? $model->getField($args[1]) : $args[1];
                $this->initQueryConditions($model, $query);
                $model->hook('initSelectQuery', [$query, $type]);

                if ($type == 'fx') {
                    $expr = "$fx([])";
                } else {
                    $expr = "coalesce($fx([]), 0)";
                }

                if (isset($args['alias'])) {
                    $query->reset('field')->field($query->expr($expr, [$field]), $args['alias']);
                } elseif ($field instanceof Field_SQL_Expression) {
                    $query->reset('field')->field($query->expr($expr, [$field]), $fx.'_'.$field->short_name);
                } else {
                    $query->reset('field')->field($query->expr($expr, [$field]));
                }

                return $query;

            default:
                throw new Exception([
                    'Unsupported action mode',
                    'type' => $type,
                ]);
        }

        $this->initQueryConditions($model, $query);
        $this->setLimitOrder($model, $query);
        $model->hook('initSelectQuery', [$query, $type]);

        return $query;
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
        if (!$m->id_field) {
            throw new Exception(['Unable to load field by "id" when Model->id_field is not defined.', 'id'=>$id]);
        }

        $load = $m->action('select');
        $load->where($m->getField($m->id_field), $id);
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

        if (!isset($data[$m->id_field]) || is_null($data[$m->id_field])) {
            throw new Exception([
                'Model uses "id_field" but it wasn\'t available in the database',
                'model'       => $m,
                'id_field'    => $m->id_field,
                'id'          => $id,
                'data'        => $data,
            ]);
        }

        $m->id = $data[$m->id_field];

        return $data;
    }

    /**
     * Loads a record from model and returns a associative array.
     *
     * @param Model $model
     * @param mixed $id
     *
     * @return array
     */
    public function load(Model $model, $id)
    {
        $data = $this->tryLoad($model, $id);

        if (!$data) {
            throw new Exception([
                'Record was not found',
                'model'      => $model,
                'id'         => $id,
                'conditions' => $model->conditions,
            ], 404);
        }

        return $data;
    }

    /**
     * Tries to load any one record.
     *
     * @param Model $model
     *
     * @return array
     */
    public function tryLoadAny(Model $model)
    {
        $load = $model->action('select');
        $load->limit(1);

        // execute action
        try {
            $data = $this->typecastLoadRow($model, $load->getRow());
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to load due to query error',
                'query'      => $load->getDebugQuery(false),
                'model'      => $model,
                'conditions' => $model->conditions,
            ], null, $e);
        }

        if (!$data) {
            return;
        }

        if ($model->id_field) {

            // If id_field is not set, model will be read-only
            if (isset($data[$model->id_field])) {
                $model->id = $data[$model->id_field];
            } else {
                throw new Exception([
                    'Model uses "id_field" but it was not available in the database',
                    'model'       => $model,
                    'id_field'    => $model->id_field,
                    'data'        => $data,
                ]);
            }
        }

        return $data;
    }

    /**
     * Loads any one record.
     *
     * @param Model $model
     *
     * @return array
     */
    public function loadAny(Model $model)
    {
        $data = $this->tryLoadAny($model);

        if (!$data) {
            throw new Exception([
                'No matching records were found',
                'model'      => $model,
                'conditions' => $model->conditions,
            ], 404);
        }

        return $data;
    }

    /**
     * Inserts record in database and returns new record ID.
     *
     * @param Model $model
     * @param array $data
     *
     * @return mixed
     */
    public function insert(Model $model, $data)
    {
        $insert = $model->action('insert');

        // don't set id field at all if it's NULL
        if ($model->id_field && array_key_exists($model->id_field, $data) && $data[$model->id_field] === null) {
            unset($data[$model->id_field]);
        }

        $insert->set($this->typecastSaveRow($model, $data));

        $st = null;

        try {
            $model->hook('beforeInsertQuery', [$insert]);
            $st = $insert->execute();
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to execute insert query',
                'query'      => $insert->getDebugQuery(false),
                'model'      => $model,
                'conditions' => $model->conditions,
            ], null, $e);
        }

        $model->hook('afterInsertQuery', [$insert, $st]);

        return $model->lastInsertID();
    }

    /**
     * Export all DataSet.
     *
     * @param Model      $model
     * @param array|null $fields
     * @param bool       $typecast_data Should we typecast exported data
     *
     * @return array
     */
    public function export(Model $model, $fields = null, $typecast_data = true)
    {
        $data = $model->action('select', [$fields])->get();

        if ($typecast_data) {
            $data = array_map(function ($r) use ($model) {
                return $this->typecastLoadRow($model, $r);
            }, $data);
        }

        return $data;
    }

    /**
     * Prepare iterator.
     *
     * @param Model $model
     *
     * @return \PDOStatement
     */
    public function prepareIterator(Model $model)
    {
        try {
            $export = $model->action('select');

            return $export->execute();
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to execute iteration query',
                'query'      => $export->getDebugQuery(false),
                'model'      => $model,
                'conditions' => $model->conditions,
            ], null, $e);
        }
    }

    /**
     * Updates record in database.
     *
     * @param Model $model
     * @param mixed $id
     * @param array $data
     */
    public function update(Model $model, $id, $data)
    {
        if (!$model->id_field) {
            throw new Exception(['id_field of a model is not set. Unable to update record.']);
        }

        $update = $this->initQuery($model);
        $update->mode('update');

        $data = $this->typecastSaveRow($model, $data);

        // only apply fields that has been modified
        $update->set($data);
        $update->where($model->getField($model->id_field), $id);

        $st = null;

        try {
            $model->hook('beforeUpdateQuery', [$update]);
            if ($data) {
                $st = $update->execute();
            }
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to update due to query error',
                'query'      => $update->getDebugQuery(false),
                'model'      => $model,
                'conditions' => $model->conditions,
            ], null, $e);
        }

        if ($model->id_field && isset($data[$model->id_field]) && $model->dirty[$model->id_field]) {
            // ID was changed
            $model->id = $data[$model->id_field];
        }

        $model->hook('afterUpdateQuery', [$update, $st]);

        // if any rows were updated in database, and we had expressions, reload
        if ($model->reload_after_save === true && (!$st || $st->rowCount())) {
            $d = $model->dirty;
            $model->reload();
            $model->_dirty_after_reload = $model->dirty;
            $model->dirty = $d;
        }
    }

    /**
     * Deletes record from database.
     *
     * @param Model $model
     * @param mixed $id
     */
    public function delete(Model $model, $id)
    {
        if (!$model->id_field) {
            throw new Exception(['id_field of a model is not set. Unable to delete record.']);
        }

        $delete = $this->initQuery($model);
        $delete->mode('delete');
        $delete->where($model->id_field, $id);
        $model->hook('beforeDeleteQuery', [$delete]);

        try {
            $delete->execute();
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to delete due to query error',
                'query'      => $delete->getDebugQuery(false),
                'model'      => $model,
                'conditions' => $model->conditions,
            ], null, $e);
        }
    }

    public function getFieldSQLExpression(Field $field, Expression $expression)
    {
        if (isset($field->owner->persistence_data['use_table_prefixes'])) {
            $mask = '{{}}.{}';
            $prop = [
                $field->join
                    ? ($field->join->foreign_alias ?: $field->join->short_name)
                    : ($field->owner->table_alias ?: $field->owner->table),
                $field->actual ?: $field->short_name,
            ];
        } else {
            // references set flag use_table_prefixes, so no need to check them here
            $mask = '{}';
            $prop = [
                $field->actual ?: $field->short_name,
            ];
        }

        // If our Model has expr() method (inherited from Persistence_SQL) then use it
        if ($field->owner->hasMethod('expr')) {
            $field->owner->expr($mask, $prop);
        }

        // Otherwise call method from expression
        return $expression->expr($mask, $prop);
    }
}
