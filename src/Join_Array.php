<?php

namespace atk4\data;

class Join_Array extends Join {

    /**
     * This method is to figure out stuff
     */
    function init()
    {
        parent::init();

        // If kind is not specified, figure out join type
        if (!isset($this->kind)) {
            $this->kind = $this->weak?'left':'inner';
        }

        // Add necessary hooks
        if ($this->reverse) {
            $this->owner->addHook('afterInsert', $this, null, -5);
            $this->owner->addHook('beforeModify', $this, null, -5);
            $this->owner->addHook('beforeDelete', [$this, 'doDelete'], null, -5);
        } else {
            $this->owner->addHook('beforeInsert', $this);
            $this->owner->addHook('beforeModify', $this);
            $this->owner->addHook('afterDelete', [$this, 'doDelete']);
            $this->owner->addHook('afterLoad', $this);
        }
    }

    function afterLoad($model)
    {
        // we need to collect ID
        $this->id = $model->data[$this->master_field];
        if (!$this->id) return;

        $data = $model->persistence->load($model, $this->id, $this->foreign_table);
        $model->data = array_merge($data, $model->data);
    }

    function afterUnload($model)
    {
        $this->id = null;
    }


    function beforeInsert($model, &$data)
    {
        if ($this->weak) {
            return;
        }

        if ($model->hasElement($this->master_field) 
            && $model[$this->master_field]
        ) {
            // The value for the master_field is set, 
            // we are going to use existing record.
            return;
        }

        // Figure out where are we going to save data
        $persistence = $this->persistence ?: 
            $this->owner->persistence;

        $this->id = $persistence->insert(
            $model, 
            $this->save_buffer, 
            $this->foreign_table
        );

        $data[$this->master_field] = $this->id;

        //$this->owner->set($this->master_field, $this->id);
    }

    function afterInsert($model, $id)
    {
        if ($this->weak) {
            return;
        }

        $this->save_buffer[$this->foreign_field] = 
            isset($this->join) ? $this->join->id : $id;

        $persistence = $this->persistence ?: 
            $this->owner->persistence;

        $this->id = $persistence->insert(
            $model, 
            $this->save_buffer, 
            $this->foreign_table
        );
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


}

