<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Class description?
 */
class Persistence
{
    use \atk4\core\ContainerTrait {
        add as _add;
    }
    use \atk4\core\FactoryTrait;
    use \atk4\core\HookTrait;
    use \atk4\core\AppScopeTrait;
    use \atk4\core\NameTrait;

    /**
     * Connects database.
     *
     * @param string $dsn
     * @param string $user
     * @param string $password
     * @param array  $args
     *
     * @return Persistence
     */
    public static function connect($dsn, $user = null, $password = null, $args = [])
    {
        if (strpos($dsn, ':') === false) {
            throw new Exception(["Your DSN format is invalid. Must be in 'driver:host:options' format", 'dsn' => $dsn]);
        }
        $driver = explode(':', $dsn, 2)[0];

        switch (strtolower(isset($args['driver']) ?: $driver)) {
            case 'mysql':
            case 'dumper':
            case 'counter':
            case 'sqlite':
                return new Persistence_SQL($dsn, $user, $password, $args);
            default:
                throw new Exception([
                    'Unable to determine persistence driver from DSN',
                    'dsn' => $dsn,
                ]);
        }
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
        if (isset($defaults[0])) {
            $m->table = $defaults[0];
            unset($defaults[0]);
        }

        $m = $this->factory($m, $defaults);

        if ($m->persistence) {
            if ($m->persistence === $this) {
                return $m;
            }

            throw new Exception([
                'Model is already related to another persistence',
            ]);
        }

        $m->setDefaults($defaults);
        $m->persistence = $this;
        $m->persistence_data = [];
        $this->initPersistence($m);
        $m = $this->_add($m, $defaults);

        $this->hook('afterAdd', [$m]);

        return $m;
    }

    /**
     * Extend this method to enhance model to work with your persistence. Here
     * you can define additional methods or store additional data. This method
     * is executed before model's init().
     *
     * @param Model $m
     */
    protected function initPersistence(Model $m)
    {
    }

    /**
     * Atomic executes operations within one begin/end transaction. Not all
     * persistences will support atomic operations, so by default we just
     * don't do anything.
     *
     * @param callable $f
     *
     * @return mixed
     */
    public function atomic($f)
    {
        return call_user_func($f);
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
        // use typecast = false to disable typecasting entirely
        if ($f->typecast === false) {
            return $value;
        }

        // use typecast = [callback, callback]
        if (is_array($f->typecast) && isset($f->typecast[0])) {
            $t = $f->typecast[0];
            return $t($value, $f, $this;);
        }

        // we respect null values.
        if ($value === null) {
            return;
        }

        // only string type fields can use empty string as legit value, for all
        // other field types empty value is the same as no-value, nothing or null
        if ($f->type != 'string' && $value === '') {
            return;
        }

        return $this->_typecastSaveField($f, $value);
    }

    /**
     * This is the actual typecasting, which you can override in your persistence
     * to implement necessary typecasting.
     */
    public function _typecastSaveField(Field $f, $value)
    {
        switch ($this->type)
        {
        case 'array':
            if(!is_array($value)) {
                throw new Exception([
                    'This field can only store arrays',
                    'field'=>$f, 'value'=>value
                ]);
            }


            // MOVE THIS TO SQL
            if(!$this->serialize === null) {
                // even though serialize wasn't defined, we have to serialize
                $value = json_encode($value);
            }

        }
        return $value;
    }

    // TODO: add typecastLoadField and _typecastLoadField
    // TODO: rename typecastLoadField in Persistence_SQL

    /**
     * Provided with a value, will perform field serialization. Can be used for
     * the purposes of encryption or storing unsupported formats 
     */
    public serializeSaveField(Field $f, $value)
    {
        // use serialize = false to disable serializing entirely
        if ($f->serialize === false) {
            return $value;
        }

        // use typecast = [callback, callback]
        if (is_array($f->serialize) && isset($f->serialize[0])) {
            $s = $f->serialize[0];
            return $s($value, $f, $this;);
        }

        return $this->_serializeSaveField(Field $f, $value);
    }

    /**
     * Override this to fine-tune for your persistence
     */
    public serializeSaveField(Field $f, $value)
    {
        switch ($this->serialize) {
        case 'json':
            // TODO, check $this->type when unserializing to adjust 2nd argument
            return json_encode($f->typecast? $this->typecastSaveField($value):$value);
        case true:
        case 'serialize':
            return serialize($f->typecast? $this->typecastSaveField($value):$value);
        case 'base64':
            return base64_encode($this->typecastSaveField($value));
        }
    }

    public serializeLoadField(Field $f, $value)
    {
    }
}
