<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

class Field_Many
{
    use \atk4\core\TrackableTrait {
        init as _init;
    }
    use \atk4\core\InitializerTrait;

    /**
     * Use this alias for related entity by default
     */
    protected $table_alias;

    /**
     * What should we pass into owner->ref() to get
     * through to this reference.
     */
    protected $link;

    /**
     * Definition of the destination model, that can
     * be either an object, a callback or a string.
     */
    protected $model;

    /**
     * their field will be $table.'_id' by default.
     */
    protected $their_field = null;

    /**
     * our field will be 'id' by default.
     */
    protected $our_field = null;

    /**
     * default constructor. Will copy argument into properties.
     */
    public function __construct($defaults = [])
    {
        if (isset($defaults[0])) {
            $this->link = $defaults[0];
            unset($defaults[0]);
        }

        foreach ($defaults as $key => $val) {
            $this->$key = $val;
        }

        if (!$this->model) {
            $this->model = $this->link;
        }
    }

    /**
     * Will use either foreign_alias or create #join_<table>.
     */
    public function getDesiredName()
    {
        return '#ref_'.$this->link;
    }

    public function init()
    {
        $this->_init();
    }

    protected function getModel($defaults = [])
    {
        if (!isset($defaults['table_alias'])) {
            if (!$this->table_alias) {
                $this->table_alias = $this->link;
                $this->table_alias = preg_replace('/_id/','',$this->table_alias);
                $this->table_alias = preg_replace('/([a-zA-Z])[a-zA-Z]*[^a-zA-Z]*/','\1',$this->table_alias);
            }
            $defaults['table_alias']=$this->table_alias;
        }

        if (is_object($this->model) && $this->model instanceof \Closure) {
            $c = $this->model;

            $c = $c($this->owner, $this);
            if (!$c->persistence && $this->owner->persistence) {
                $c = $this->owner->persistence->add($c, $defaults);
            }
            return $c;
        }

        if (is_object($this->model)) {
            if ($this->model->persistence || !$this->owner->persistence) {
                return clone $this->model;
            }
            $c = clone $this->model;
            return $this->owner->persistence->add($c, $defaults);
        }

        // last effort - try to add model
        $p = $this->owner->persistence;

        return $p->add($p->normalizeClassName($this->model, 'Model'), $defaults);

        throw new Exception([
            'Model is not defined for the relation',
            'model' => $this->model,
        ]);
    }

    protected function getOurValue()
    {
        if ($this->owner->loaded()) {
            if ($this->our_field) {
                return $this->owner[$this->our_field];
            } else {
                return $this->owner->id;
            }
        } else {
            // create expression based on exsting conditions
            return $this->owner->action(
                'field', [
                    $this->our_field ?: $this->owner->id_field,
                ]);
        }
    }

    protected function referenceOurValue()
    {
        $this->owner->persistence_data['use_table_prefixes'] = true;

        return $this->owner->getElement($this->our_field ?: $this->owner->id_field);
    }

    /**
     * Adding field into join will automatically associate that field
     * with this join. That means it won't be loaded from $table but
     * form the join instead.
     */
    public function ref($defaults = [])
    {
        return $this->getModel($defaults)
            ->addCondition(
                $this->their_field ?: ($this->owner->table.'_id'),
                $this->getOurValue()
            );
    }

    /**
     * Creates model that can be used for generating sub-query acitons.
     */
    public function refLink($defaults = [])
    {
        return $this->getModel($defaults)
            ->addCondition(
                $this->their_field ?: ($this->owner->table.'_id'),
                $this->referenceOurValue()
            );
    }

    /**
     * Adding field into join will automatically associate that field
     * with this join. That means it won't be loaded from $table but
     * form the join instead.
     */
    public function addField($n, $defaults = [])
    {
        if (!isset($defaults['aggregate'])) {
            throw new Exception([
                '"aggregate" strategy should be defined for oneToMany field',
                'field'    => $n,
                'defaults' => $defaults,
            ]);
        }

        $field = isset($defaults['field']) ? $defaults['field'] : $n;

        return $this->owner->addExpression($n, function($m) use ($defaults, $field) {
            return $this->refLink()->action('fx', [$defaults['aggregate'], $field]);
        });
    }

    public function addFields($fields = [])
    {
        foreach ($fields as $field) {
            $name = $field[0];
            unset($field[0]);
            $this->addField($name, $field);
        }

        return $this;
    }

    // {{{ Debug Methods
    public function __debugInfo()
    {
        $arr = [
            'ref'     => $this->link,
            'model'   => $this->model,
        ];

        if ($this->our_field) {
            $arr['our_field'] = $this->our_field;
        }

        if ($this->their_field) {
            $arr['their_field'] = $this->their_field;
        }

        return $arr;
    }

    // }}}
}
