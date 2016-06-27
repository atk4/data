<?php

namespace atk4\data;

class Persistence_SQL extends Persistence {

    // atk4\dsql\Connection
    public $connection;

    function __construct($connection, $user = null, $password = null, $args = [])
    {
        if ($connection instanceof \atk4\dsql\Connection) {
            $this->connection = $connection;
            return;
        }

        if (is_object($connection)) {
            throw new Exception([
                'You can only use Persistance_SQL with Connection class from atk4\dsql',
                'connection'=>$connection
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

    public function add($m, $defaults = [])
    {
        // Use our own classes for fields, relations and expressions unless
        // $defaults specify them otherwise.
        $defaults = array_merge([
            '_default_class_addField' => 'atk4\data\Field_SQL',
            '_default_class_hasOne' => 'atk4\data\Field_SQL_Reference',
            '_default_class_addExpression' => 'atk4\data\Field_SQL_Expression',
            '_default_class_join' => 'atk4\data\Join_SQL',
        ], $defaults);

        $m = parent::add($m, $defaults);


        if (!$m->table) {
            throw new Exception([
                'Property $table must be specified for a model',
                'model'=>$m
            ]);
        }

        //$m->addMethod('action', $this);
    }

    /**
     * Initializes base query for model $m
     */
    public function initQuery($m)
    {
        $d = $m->persistence_data['dsql'] = $this->connection->dsql();

        if (isset($m->table_alias)) {
            $d->table($m->table, $m->table_alias);
        } else {
            $d->table($m->table);
        }

        return $d;
    }

    public function initQueryFields($m, $q)
    {
        if ($m->only_fields) {
            foreach($m->only_fields as $field) {
                $q->field($m->getElement($field));
            }
        }else{
            foreach($m->elements as $field => $f_object) {
                if ($f_object instanceof Field_SQL) {
                    $q->field($f_object);
                }
            }
        }
    }

    /**
     * Will apply conditions defined inside $m onto query $q
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
    }

    /**
     * Executing $model->aciton('update') will call
     * this method
     */
    public function action($m, $type)
    {
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
                break;

            case 'select':
                break;

            default:
                throw new Exception([
                    'Unsupported action mode',
                    'type'=>$type
                ]);
        }

        $this->initQueryConditions($m, $q);
        $this->initQueryFields($m, $q);
        $m->hook('initSelectQuery', [$q]);
        return $q;
    }

    /**
     * Generates action that performs load of the record $id
     * and returns requested fields
     */
    public function load(Model $m, $id)
    {
        $load = $this->action($m, 'select', [$id]);
        $load->where($m->getElement($m->id_field), $id);
        $load->limit(1);

        // execute action
        $data = $load->getRow();

        if (!$data) {
            throw new Exception([
                'Unable to load record',
                'model'=>$m,
                'id'=>$id
            ]);
        }

        if (isset($data[$m->id_field])) {
            $m->id = $data[$m->id_field];
        } else {
            throw new Exception([
                'ID of the record is unavailable. Read-only mode is not supported',
                'model'=>$m,
                'id'=>$id,
                'data'=>$data
            ]);
        }

        return $data;
    }

    public function tryLoad(Model $m, $id)
    {

        $load = $this->action($m, 'select', [$id]);
        $load->where($m->getElement($m->id_field), $id);
        $load->limit(1);

        // execute action
        $data = $load->getRow();

        if (!$data) {
            return $m->unload();
        }

        if (isset($data[$m->id_field])) {
            $m->id = $data[$m->id_field];
        } else {
            throw new Exception([
                'ID of the record is unavailable. Read-only mode is not supported',
                'model'=>$m,
                'id'=>$id,
                'data'=>$data
            ]);
        }

        $m->data = $data;
    }

    public function insert(Model $m, $data)
    {
        $insert = $this->action($m, 'insert');

        // apply all fields we got from get
        foreach($data as $field => $value) {
            $f = $m->getElement($field);
            $insert->set($f->actual ?: $f->short_name, $value);
        }

        $m->hook('beforeInsertQuery',[$insert]);

        $insert->execute();
    }

    public function update(Model $m, $id, $data)
    {
        $update = $this->action($m, 'update');

        // only apply fields that has been modified
        foreach($data as $field => $value) {
            $f = $m->getElement($field);
            $update->set($f->actual ?: $f->short_name, $value);
        }
        $update->where($m->getElement($m->id_field), $id);

        $update->execute();
    }
}
