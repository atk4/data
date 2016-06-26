<?php
namespace atk4\data\tests;

use atk4\dsql\Expression;
use atk4\dsql\Exception;

class Structure extends Expression
{

    public $mode = 'create';

    protected $templates = [
        'create'=>'create table {table} ([field])',
        'drop'=>'drop table if exists {table}',
    ];

    function table($table)
    {
        $this['table'] = $table;
        return $this;
    }


    function field($name, $options = [])
    {
        // save field in args
        $this->_set_args('field', $name, $options);
        return $this;
    }

    function id($name = null)
    {
        if (!$name) {
            $name = 'id';
        }

        $val = $this->expr('integer primary key autoincrement');

        $this->args['field'] = 
            [$name => $val] + (isset($this->args['field']) ? $this->args['field'] : []);

        return $this;
    }

    function _render_field()
    {

        $ret = [];

        if (!$this->args['field']) {
            throw new Exception([
                'No fields defined for table', 
            ]);
        }

        foreach ($this->args['field'] as $field=>$options) {


            if ($options instanceof Expression) {
                $ret[] = $this->_escape($field).' '.$this->_consume($options);
                continue;
            }

            $type = strtolower(isset($options['type'])?
                $options['type'] : 'varchar');
            $type = preg_replace('/[^a-z0-9]+/', '', $type);

            $len = isset($options['len']) ?
                $options['len'] : 
                ($type === 'varchar' ? 255: null);

            $ret[] = $this->_escape($field).' '.$type.
                ($len ? ('('.$len.')') : '');
        }

        return implode(',', $ret);
    }

    function mode($mode)
    {
        if (!isset($this->templates[$mode])) {
            throw new Exception(['Structure builder does not have this mode', 'mode' => $mode]);
        }

        $this->mode=$mode;
        $this->template=$this->templates[$mode];

        return $this;
    }

    function drop()
    {
        $this->mode('drop')->execute();
    }

    function create()
    {
        $this->mode('create')->execute();
    }


    /**
     * Sets value in args array. Doesn't allow duplicate aliases.
     *
     * @param string $what Where to set it - table|field
     * @param string $alias Alias name
     * @param mixed $value Value to set in args array
     */
    protected function _set_args($what, $alias, $value)
    {
        // save value in args
        if ($alias === null) {
            $this->args[$what][] = $value;
        } else {

            // don't allow multiple values with same alias
            if (isset($this->args[$what][$alias])) {
                throw new Exception([
                    ucfirst($what) . ' alias should be unique',
                    'alias' => $alias
                ]);
            }

            $this->args[$what][$alias] = $value;
        }
    }
}
