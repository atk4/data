<?php // vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

class Model implements \ArrayAccess
{
    use \atk4\core\ContainerTrait;
    use \atk4\core\DynamicMethodTrait;
    use \atk4\core\HookTrait;
    use \atk4\core\InitializerTrait {
        init as _init;
    }

    // {{{ Properties of the class

    /**
     * The class used by addField() method
     */
    protected $_default_class_addField = 'atk4\data\Field';

    /**
     * The class used by hasOne() method
     */
    protected $_default_class_hasOne = 'atk4\data\Field_Reference';

    /**
     * The class used by hasMany() method
     */
    protected $_default_class_hasMany = 'atk4\data\Field_Many';

    /**
     * The class used by addField() method
     */
    protected $_default_class_addExpression = 'atk4\data\Field_Callback';

    protected $_default_class_join = 'atk4\data\Join';

    /**
     * Contains name of table, session key, collection or file where this
     * model normally lives. The interpretation of the table will be decoded
     * by persistence driver.
     *
     * You can define this field as associative array where "key" is used
     * as the name of pesistence driver. Here is example for mysql and default:
     *
     * $table = ['user', 'mysql'=>'tbl_user'];
     *
     * @var string|array
     */
    public $table = null;

    /**
     * Persistence driver inherited from atk4\data\Persistence
     */
    public $persistence = null;

    /**
     * Persistence store some custom information in here that may be useful
     * for them. The key is the name of persistence driver.
     *
     * @var array
     */
    public $persistence_data = [];

    /**
     * Conditions list several conditions that must be met by all the
     * records in the associated DataSet. Conditions are stored as
     * elements of array of 1 to 3. Use addCondition() to add new
     * conditions.
     */
    public $conditions = [];

    public $limit;

    public $order;

    /**
     * Curretly loaded record data. This record is associative array
     * that contain field=>data pairs. It may contain data for un-defined
     * fields only if $_onlyFieldsMode is false.
     *
     * Avoid accessing $data directly, use set() / get() instead.
     *
     * @var array
     */
    public $data = array();

    /**
     * After loading an active record from DataSet it will be stored in
     * $data property and you can access it using get(). If you use
     * set() to change any of the data, the original value will be copied
     * here.
     *
     * If the value you set equal to the original value, then the key
     * in this array will be removed.
     *
     * The $dirty data will be reset after you save() the data but it is
     * still available to all before/after save handlers.
     *
     * @var array
     */
    public $dirty = [];


    /**
     * Contains ID of the curent record. If the value is null then the record
     * is considered to be new.
     */
    public $id = null;

    /**
     * While in most cases your id field will be called 'id', sometimes
     * you would want to use a different one or maybe don't create field
     * at all.
     */
    public $id_field = 'id';

    /**
     * When using onlyFields() this property will contain list of desired
     * fields.
     *
     * When you have used onlyFields() before loading the data for this
     * model, then only that set of fields will be available. Attempt
     * to access any other field will result in exception. This is to ensure
     * that you do not accidentally access field that you have explicitly
     * excluded.
     *
     * The default behaviour is to return NULL and allow you to set new
     * fields even if addField() was not used to set the field.
     */
    public $only_fields = false;

    // }}}

    // {{{ Basic Functionality, field definition, set() and get()

    /**
     * Creation of the new model can be done in two ways:
     *
     * $m = $db->add(new Model());
     *
     * or
     *
     * $m = new Model($db);
     *
     * The second use actually calls add() but is prefered usage because:
     *  - it's shorter
     *  - type hinting will work;
     */
    function __construct($persistence = null, $defaults = [])
    {

        if (is_string($defaults) || $defaults === false) {
            $defaults = [$defaults];
        }

        if (is_array($persistence)) {
            $defaults = $persistence;
            $persistence = null;
        }

        foreach ($defaults as $key => $val) {
            $this->$key = $val;
        }

        if ($persistence) {
            $persistence->add($this, $defaults);
        }

    }

    function setDefaults($defaults){
        foreach ($defaults as $key => $val) {
            $this->$key = $val;
        }
    }

    /**
     * Extend this method to define fields of your choice
     */
    public function init()
    {
        $this->_init();

        if ($this->id_field) {
            $this->addField($this->id_field, ['system'=>true, 'type'=>'int']);
        }
    }


    public function addField($name, $defaults = [])
    {
        $c = $this->_default_class_addField;
        $field = new $c($defaults);
        $this->add($field, $name);
        return $field;
    }

    public function addFields($fields = [])
    {
        foreach ($fields as $field) {
            if (is_string($field)) {
                $this->addField($field);
                continue;
            }

            $name = $field[0];
            unset($field[0]);
            $this->addField($name, $field);
        }
        return $this;
    }


    public function onlyFields($fields = [])
    {
        $this->hook('onlyFields',[&$fields]);
        $this->only_fields = $fields;
    }

    public function allFields()
    {
        $this->only_fields = false;
    }

    private function normalizeFieldName($field)
    {
        // $m->set($m->getElement('name'), 'John')
        if (
            is_object($field)
            && isset($field->_trackableTrait)
            && $field->owner === $this
        ) {
            $field = $field->short_name;
        }

        if (!is_string($field)) {
            throw new Exception([
                'Incorect specification of field name',
                'arg'=>$field
            ]);
        }

        // $m->onlyFields(['name'])->set('surname', 'Jane');
        if ($this->only_fields) {
            if (!in_array($field, $this->only_fields)) {

                throw new Exception([
                    'Attempt to use field outside of those set by onlyFields',
                    'field'=>$field,
                    'only_fields'=>$this->only_fields
                ]);
            }
        }
        return $field;
    }

    public function set($field, $value = null)
    {
        // set(['foo'=>'bar']) will call itself as set('foo', 'bar');
        if (func_num_args() == 1) {
            if (is_array($field)) {
                foreach ($field as $key=>$value) {
                    $this->set($key, $value);
                }
                return $this;
            }

            throw new Exception([
                'Single argument set() requires an array argument',
                'arg'=>$field
            ]);
        }

        $field = $this->normalizeFieldName($field);

        // $m->addField('datetime', ['type'=>'date']);
        // $m['datetime'] = new DateTime('2000-01-01'); will potentially
        // convert value into unix timestamp
        $f_object = $this->hasElement($field);
        if ($f_object) {
            $f_object->hook('normalize', [$field, &$value]);
        }


        // $m['name'] = $m['name'];
        if (array_key_exists($field, $this->data) && $value === $this->data[$field]) {
            // do nothing, value unchanged
            return $this;
        }

        if (array_key_exists($field, $this->dirty) && $this->dirty[$field] === $value) {
            unset($this->dirty[$field]);
            $this->data[$field] = $value;
        } else {
            $this->dirty[$field] =
                array_key_exists($field, $this->data) ?
                $this->data[$field] :
                (
                    $f_object ? $f_object->getDefault() : null
                );

            $this->data[$field] = $value;
        }

        if ($field === $this->id_field) {
            $this->id = $value;
        }

        return $this;
    }

    public function get($field = null)
    {
        if ($field === null) {

            // Collect list of eligible fields
            $data = [];
            if ($this->only_fields) {
                // collect data for actual fields
                foreach($this->only_fields as $field) {
                    $data[$field] = $this->get($field);
                }
            } else {
                // get all field-elements
                foreach($this->elements as $field => $f_object) {
                    if ($f_object instanceof Field) {
                        $data[$field] = $this->get($field);
                    }
                }
            }
            return $data;
        }

        $field = $this->normalizeFieldName($field);


        $f_object = $this->hasElement($field);

        $value =
            array_key_exists($field, $this->data) ?
            $this->data[$field] :
            (
                $f_object ?
                $f_object->getDefault() :
                null
            );

        if ($f_object) {
            $f_object->hook('get', [$field, &$value]);
        }

        return $value;
    }
    // }}}

    // {{{ ArrayAccess support
    public function offsetExists($name)
    {
        return array_key_exists($this->normalizeFieldName($name), $this->dirty);
    }
    public function offsetGet($name)
    {
        return $this->get($name);
    }
    public function offsetSet($name, $val)
    {
        $this->set($name, $val);
    }
    public function offsetUnset($name)
    {
        $name = $this->normalizeFieldName($name);
        if (array_key_exists($name, $this->dirty)) {
            $this->data[$name] = $this->dirty[$name];
            unset($this->dirty[$name]);
        }
    }
    // }}}

    // {{{ DataSet logic
    /**
     * Narrow down data-set of the current model by applying
     * additional condition. There is no way to remove
     * condition once added, so if you need - clone model.
     *
     * This is the most basic for for defining condition: 
     *  ->addCondition('my_field', $value);
     *
     * This condition will work across all persistence drivers universally.
     *
     * In some cases a more complex logic can be used:
     *  ->addCondition('my_field', '>', $value);
     *  ->addCondition('my_field', '!=', $value);
     *  ->addCondition('my_field', 'in', [$value1, $value2]);
     *
     * Second argument could be '=', '>', '<', '>=', '<=', '!=' or 'in'.
     * Those conditons are still supported by most of persistence drivers.
     *
     * There are also vendor-specific expression support:
     *  ->addCondition('my_field', $expr);
     *  ->addCondition($expr);
     *
     * To use those, you should consult with documentation of your
     * persistence driver. 
     */
    public function addCondition($field, $operator = null, $value = null)
    {
        if (is_array($field)) {
            array_map(function($a){
                call_user_func_array([$this, 'addCondition'], $a);
            }, $field);
            return $this;
        }

        $this->conditions[] = func_get_args();
        return $this;
    }
    // }}}


    // {{{ Persistence-related logic
    public function loaded()
    {
        return $this->id!==null;
    }

    public function unload()
    {
        $this->id = null;
        $this->data = [];
        $this->dirty = [];
        return $this;
    }


    public function load($id)
    {
        if (!$this->persistence) {
            throw new Exception([
                'Model is not associated with any database'
            ]);
        }

        if ($this->loaded()) {
            $this->unload();
        }

        $this->data = $this->persistence->load($this, $id);
        $this->id = $id;
        $this->hook('afterLoad');

        return $this;
    }

    public function tryLoad($id)
    {
        if (!$this->persistence) {
            throw new Exception([
                'Model is not associated with any database'
            ]);
        }

        if ($this->loaded()) {
            $this->unload();
        }

        $this->data = $this->persistence->tryLoad($this, $id);
        if ($this->data) {
            $this->id = $id;
            $this->hook('afterLoad');
        } else {
            $this->unload();
        }

        return $this;
    }

    public function tryLoadAny()
    {
        if (!$this->persistence) {
            throw new Exception([
                'Model is not associated with any database'
            ]);
        }

        if ($this->loaded()) {
            $this->unload();
        }

        $this->data = $this->persistence->tryLoadAny($this);
        if($this->data){
            $this->id = $this->data[$this->id_field];
            $this->hook('afterLoad');
        }else{
            $this->unload();
        }

        return $this;
    }



    public function save()
    {
        if (!$this->persistence) {
            throw new Exception([
                'Model is not associated with any database'
            ]);
        }

        $is_update = $this->loaded();
        if ($is_update) {
            $data = array();
            foreach ($this->dirty as $name => $junk) {
                $field = $this->hasElement($name);
                if (!$field) {
                    continue;
                }

                // get actual name of the field
                $actual = $field->actual ?: $name;

                // get the value of the field
                $value = $this->get($name);

                if (isset($field->join)) {
                    // storing into a different table join
                    $field->join->set($actual, $value);
                } else {
                    $data[$actual] = $value;
                }
            }

            // No save needed, nothing was changed
            if (!$data) {
                return $this;
            }

            $this->hook('beforeModify',[&$data]);

            $this->persistence->update($this, $this->id, $data);

            $this->hook('afterModify',[&$data]);

            //$this->hook('beforeUpdate', array(&$source));
        } else {

            foreach ($this->get() as $name => $value) {

                $field = $this->hasElement($name);
                if (!$field) {
                    continue;
                }

                // get actual name of the field
                $actual = $field->actual ?: $name;

                if (isset($field->join)) {
                    // storing into a different table join
                    $field->join->set($actual, $value);
                } else {
                    $data[$actual] = $value;
                }
            }

            $this->hook('beforeInsert',[&$data]);

            // Collect all data of a new record
            $this->id = $this->persistence->insert($this, $data);
            $this->hook('afterInsert',[$this->id]);

            //$this->hook('beforeInsert', array(&$source));
        }


        if ($this->loaded()) {
            $this->dirty = [];
        }

        return $this;
    }

    /**
     * Faster method to add data, that does not modify active record
     */
    public function insert($data)
    {
        $m = clone $this;
        $m->unload();
        $m->set($data);
        $m->save();
        return $m->id;
    }

    /**
     * Delete record with a specified id. If no ID is specified
     * then current record is deleted.
     */
    public function delete($id = null)
    {
        if ($id == $this->id) {
            $id = null;
        }

        if ($id) {

            $this->persistence->delete($this, $id);

        } elseif ($this->loaded()) {

            $this->hook('beforeDelete',[$id]);
            $this->persistence->delete($this, $this->id);
            $this->hook('afterDelete',[$id]);
            $this->unload();
        } else {
            throw new Exception(['No active record is set, unable to delete.']);
        }
    }
    // }}}

    // {{{ Support for actions
    public function action($mode, $args = [])
    {
        if (!$this->persistence) {
            throw new Exception(['action() requires model to be associated with db']);
        }

        return $this->persistence->action($this, $mode, $args);
    }
    // }}}

    // {{{ Join support
    /**
     * Creates an objects that describes relationship between multiple tables (or collections)
     *
     * When object is loaded, then instead of pulling all the data from a single table,
     * join will also query $foreign table in order to find additional fields. When inserting
     * the record will be also added inside $foreign_table and relationship will be maintained
     */
    public function join($foreign_table, $defaults = [])
    {
        if (!is_array($defaults)) {
            $defaults = ['master_field' => $defaults];
        } elseif (isset($defaults[0])) {
            $defaults['master_field'] = $defaults[0];
            unset($defaults[0]);
        }

        $defaults[0] = $foreign_table;

        $c = $this->_default_class_join;
        return $this->add(new $c($defaults));
    }
    // }}}

    // {{{ Relations
    protected function _hasSomething($c, $link, $defaults = [])
    {
        if (!is_array($defaults)) {

            if ($defaults) {
                $defaults = ['model'=>$defaults];
            } else {
                $defaults = ['model'=>'Model_'.$link];
            }
        } elseif(isset($defaults[0])) {
            $defaults['model'] = $defaults[0];
            unset($defaults[0]);
        }

        $defaults[0] = $link;

        return $this->add(new $c($defaults));
    }

    public function hasOne($link, $defaults = [])
    {
        return $this->_hasSomething($this->_default_class_hasOne, $link, $defaults);
    }

    public function hasMany($link, $defaults = [])
    {
        return $this->_hasSomething($this->_default_class_hasMany, $link, $defaults);
    }

    public function ref($link)
    {
        return $this->getElement('#ref_'.$link)->ref();
    }

    public function refLink($link)
    {
        return $this->getElement('#ref_'.$link)->refLink();
    }
    // }}}

    // {{{ Expressions
    public function addExpression($name, $defaults) {

        if (!is_array($defaults)) {
            $defaults = ['expr' => $defaults];
        } elseif (isset($defaults[0])) {
            $defaults['expr'] = $defaults[0];
            unset($defaults[0]);
        }

        $c = $this->_default_class_addExpression;
        return $this->add(new $c($defaults), $name);
    }
    // }}}
}
