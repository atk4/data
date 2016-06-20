<?php

namespace atk4\data;

class Join_SQL extends Join {

    protected $foreign_alias;
    /**
     * A short symbolic name that will be used as an alias for the joined table
     */

    /**
     * By default this will be either "inner" (for strong) or "left" for weak joins.
     * You can specify your own type of join by passing ['kind'=>'right']
     * as second argument to join().
     */
    protected $kind;

    /**
     * By default we create ON expresison ourselves, but if you want to specify
     * it, use the 'on' property.
     */
    protected $on = null;

    /**
     * Query we are building
     */
    protected $dsql = null;

    /**
     * Will use either foreign_alias or create #join_<table> 
     */
    public function getDesiredName()
    {
        return '_'.($this->foreign_alias ?: $this->foreign_table[0]);
    }

    /**
     * This method is to figure out stuff
     */
    function init()
    {
        parent::init();

        $this->dsql = $this->owner->dsql();

        // If kind is not specified, figure out join type
        if (!isset($this->kind)) {
            $this->kind = $this->weak?'left':'inner';
        }

        // Our short name will be unique
        if (!$this->foreign_alias) {
            $this->foreign_alias = $this->short_name;
        }

        // Add necessary hooks
        if ($this->reverse) {
            $this->owner->addHook('beforeInsert', $this);
            $this->owner->addHook('beforeModify', $this);
            $this->owner->addHook('afterDelete', [$this, 'doDelete']);
        } else {
            $this->owner->addHook('afterInsert', $this, null, -5);
            $this->owner->addHook('beforeModify', $this, null, -5);
            $this->owner->addHook('beforeDelete', [$this, 'doDelete'], null, -5);
        }
    }


    /**
     * Before query is executed, this method will be called. 
     */
    function updateSelectQuery($query)
    {
        // if ON is set, we don't have to worry about anything
        if ($this->on) {
            $query->join(
                $this->foreign_table.' '.$this->foreign_alias,
                $this->on instanceof \atk4\dsql\Expression ?
                $this->on :
                $query->expr($this->on)
            );
            return;
        }

        //if ($this->reverse) {
            //$query->field([$this->short_name=>($this->join?:($this->owner->table.'.'.$this->master_field]);
        //} else {
            $query->field([$this->short_name=>$this->foreign_alias.'.'.$this->foreign_field]);
        //}
    }

    function afterLoad($model)
    {
        // we need to collect ID
        $this->id = $model->data[$this->short_name];
        unset($model->data[$this->short_name]);
    }

    function afterUnload($model)
    {
        $this->id = null;
    }


    function beforeInsert($model, $query)
    {
        if ($this->weak) {
            return;
        }

        // The value for the master_field is set, so we are going to use existing record anyway
        if ($this->model->hasElement($this->master_field) && $this->model[$this->master_field]) {
            return;
        }

        $this->dsql->set($this->foreign_field, null);
        $this->id = $this->dsql->insert();

        if ($this->join) {
            $query = $this->join->dsql;
        }

        $query->set($this->master_field, $id);
    }

    function afterInsert($model, $id)
    {
        if ($this->weak) {
            return;
        }

        $this->id = $this->dsql
            ->set(
                $this->foreign_field, 
                $this->join ? $this->join->id : $id
            )
            ->insert();
    }

    function beforeModify($model, $query)
    {
        if ($this->weak) {
            return;
        }

        if ($this->dsql->args['set']) {
            $this->dsql->where($this->foreign_field, $this->id)->update();
        }
    }

    function doDelete($model, $id)
    {
        if ($this->weak) {
            return;
        }

        $q = $model->dsql()->dsql();
        $q
            ->table($this->foreign_table)
            ->where($this->foreign_field, $this->id)
            ;

        if ($this->delete_behaivour == 'cascade') {
            $q->delete();
        } elseif ($this->delete_behaivour == 'setnull') {
            $q
                ->set($this->foreign_field, null)
                ->update();
        }
    }

    function set($field, $value)
    {
        $this->dsql->set($field, $value);
    }


}

