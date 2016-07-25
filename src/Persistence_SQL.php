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
    public $_default_class_hasOne = 'atk4\data\Field_SQL_One';

    /**
     * Default class when adding hasMany field.
     *
     * @var string
     */
    public $_default_class_hasMany = null; //'atk4\data\Field_Many';

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
     * Associate model with the data driver.
     *
     * @param Model|string $m        Model which will use this persistence
     * @param array        $defaults Properties
     *
     * @return Model
     */
    public function add($m, $defaults = [])
    {
        // Use our own classes for fields, relations and expressions unless
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
        }

        return $m;
    }

    /**
     * Initialize persistance.
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
    public function expr($m, $expr, $args = [])
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
                // really, SQL?
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
                $q->where($cond[0], $cond[1]);
            } else {
                $q->where($cond[0], $cond[1], $cond[2]);
            }
        }

        return $q;
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
                        'fx action needs 2 argumens, eg: ["sum", "amount"]',
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
     * Generates action that performs load of the record $id
     * and returns requested fields.
     *
     * @param Model $m
     * @param mixed $id
     *
     * @return array
     */
    public function load(Model $m, $id)
    {
        $load = $this->action($m, 'select');
        $load->where($m->getElement($m->id_field), $id);
        $load->limit(1);

        // execute action
        try {
            $data = $load->getRow();
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to load due to query error',
                'query'      => $load->getDebugQuery(false),
                'model'      => $m,
                'conditions' => $m->conditions,
            ], null, $e);
        }

        if (!$data) {
            throw new Exception([
                'Unable to load record',
                'model' => $m,
                'id'    => $id,
                'query' => $load->getDebugQuery(false),
            ]);
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
        $data = $load->getRow();

        if (!$data) {
            $m->unload();

            return [];
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
     * Loads any one record.
     *
     * @param Model $m
     *
     * @return array
     */
    public function loadAny(Model $m)
    {
        $load = $this->action($m, 'select');
        $load->limit(1);

        // execute action
        $data = $load->getRow();

        if (!$data) {
            throw new Exception([
                'Unable to load any record',
                'model' => $m,
                'query' => $load->getDebugQuery(false),
            ]);
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
        $data = $load->getRow();

        if (!$data) {
            return [];
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

        // apply all fields we got from get
        foreach ($data as $field => $value) {
            $f = $m->getElement($field);
            if (!$f->editable) {
                continue;
            }
            $insert->set($f->actual ?: $f->short_name, $value);
        }

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

        return $export->get();
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
        $update = $this->action($m, 'update');

        // only apply fields that has been modified
        $cnt = 0;
        foreach ($data as $field => $value) {
            $f = $m->getElement($field);
            $update->set($f->actual ?: $f->short_name, $value);
            $cnt++;
        }
        if (!$cnt) {
            return;
        }
        $update->where($m->getElement($m->id_field), $id);


        $st = null;

        try {
            $m->hook('beforeUpdateQuery', [$update]);
            $st = $update->execute();
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to update due to query error',
                'query'      => $update->getDebugQuery(false),
                'model'      => $m,
                'conditions' => $m->conditions,
            ], null, $e);
        }

        $m->hook('afterUpdateQuery', [$update, $st]);

        // if any rows were updated in database, and we had expressions, reload
        if ($m->reload_after_save === true && $st->rowCount()) {
            $m->reload();
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
        $delete = $this->action($m, 'delete');
        $delete->reset('where'); // because it could have join there..
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
