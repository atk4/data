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
     * @param string $dsn      Format as PDO DSN or use "mysql://user:pass@host/db;option=blah", leaving user and password = null
     * @param string $user
     * @param string $password
     * @param array  $args
     *
     * @return Persistence
     */
    public static function connect($dsn, $user = null, $password = null, $args = [])
    {
        // Try to dissect DSN into parts
        if (is_array($dsn)) {
            $parts = $dsn;
        } else {
            $parts = parse_url($dsn);
        }

        // If parts are usable, convert DSN format
        if ($parts !== false && isset($parts['host']) && isset($parts['path']) && $user === null && $password === null) {
            // DSN is using URL-like format, so we need to convert it
            $dsn = $parts['scheme'].':host='.$parts['host'].';dbname='.substr($parts['path'], 1);
            $user = $parts['user'];
            $password = $parts['pass'];
        }

        // Omitting UTF8 is always a bad problem, so unless it's specified we will do that to prevent nasty problems.
        if (strpos($dsn, ';charset=') === false) {
            $dsn .= ';charset=utf8';
        }

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
    public function typecastSaveRow(Model $m, $row)
    {
        if (!$row) {
            return $row;
        }

        $result = [];
        foreach ($row as $key => $value) {

            // Look up field object
            $f = $m->hasElement($key);

            // Figure out the name of the destination field
            $field = $f->actual ?: $key;

            // We have no knowledge of the field, it wasn't defined, so
            // we will leave it as-is.
            if (!$f) {
                $result[$field] = $value;
                continue;
            }

            // check null values for mandatory fields
            if ($value === null && $f->mandatory) {
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

            // typecast if we explicitly want that or there is not serialization enabled
            if ($f->typecast || ($f->typecast === null && $f->serialize === null)) {
                $value = $this->typecastSaveField($f, $value);
            }

            // serialize if we explicitly want that
            if ($f->serialize) {
                $value = $this->serializeSaveField($f, $value);
            }

            // store converted value
            $result[$field] = $value;
        }

        return $result;
    }

    /**
     * Will convert one row of data from Persistence-specific
     * types to PHP native types.
     *
     * NOTE: Please DO NOT perform "actual" field mapping here, because data
     * may be "aliased" from SQL persistences or mapped depending on persistence
     * driver.
     *
     * @param Model $m
     * @param array $row
     *
     * @return array
     */
    public function typecastLoadRow(Model $m, $row)
    {
        if (!$row) {
            return $row;
        }

        $result = [];
        foreach ($row as $key => &$value) {

            // Look up field object
            $f = $m->hasElement($key);

            // We have no knowledge of the field, it wasn't defined, so
            // we will leave it as-is.
            if (!$f) {
                $result[$key] = $value;
                continue;
            }

            // ignore null values
            if ($value === null) {
                $result[$key] = $value;
                continue;
            }

            // serialize if we explicitly want that
            if ($f->serialize) {
                $value = $this->serializeLoadField($f, $value);
            }

            // typecast if we explicitly want that or there is not serialization enabled
            if ($f->typecast || ($f->typecast === null && $f->serialize === null)) {
                $value = $this->typecastLoadField($f, $value);
            }

            // store converted value
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Prepare value of a specific field by converting it to
     * persistence-friendly format.
     *
     * @param Field $f
     * @param mixed $value
     *
     * @return mixed
     */
    public function typecastSaveField(Field $f, $value)
    {
        // use $f->typecast = [typecast_save_callback, typecast_load_callback]
        if (is_array($f->typecast) && isset($f->typecast[0]) && is_callable($t = $f->typecast[0])) {
            return $t($value, $f, $this);
        }

        // we respect null values
        if ($value === null) {
            return;
        }

        // run persistence-specific typecasting of field value
        return $this->_typecastSaveField($f, $value);
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
        // use $f->typecast = [typecast_save_callback, typecast_load_callback]
        if (is_array($f->typecast) && isset($f->typecast[1]) && is_callable($t = $f->typecast[1])) {
            return $t($value, $f, $this);
        }

        // only string type fields can use empty string as legit value, for all
        // other field types empty value is the same as no-value, nothing or null
        if ($f->type && $f->type != 'string' && $value === '') {
            return;
        }

        // we respect null values
        if ($value === null) {
            return;
        }

        // run persistence-specific typecasting of field value
        return $this->_typecastLoadField($f, $value);
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
    public function _typecastSaveField(Field $f, $value)
    {
        return $value;
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
    public function _typecastLoadField(Field $f, $value)
    {
        return $value;
    }

    /**
     * Provided with a value, will perform field serialization.
     * Can be used for the purposes of encryption or storing unsupported formats.
     *
     * @param Field $f
     * @param mixed $value
     *
     * @return mixed
     */
    public function serializeSaveField(Field $f, $value)
    {
        // use $f->serialize = [encode_callback, decode_callback]
        if (is_array($f->serialize) && isset($f->serialize[0]) && is_callable($t = $f->serialize[0])) {
            return $t($f, $value, $this);
        }

        // run persistence-specific serialization of field value
        return $this->_serializeSaveField($f, $value);
    }

    /**
     * Provided with a value, will perform field un-serialization.
     * Can be used for the purposes of encryption or storing unsupported formats.
     *
     * @param Field $f
     * @param mixed $value
     *
     * @return mixed
     */
    public function serializeLoadField(Field $f, $value)
    {
        // use $f->serialize = [encode_callback, decode_callback]
        if (is_array($f->serialize) && isset($f->serialize[1]) && is_callable($t = $f->serialize[1])) {
            return $t($f, $value, $this);
        }

        // run persistence-specific un-serialization of field value
        return $this->_serializeLoadField($f, $value);
    }

    /**
     * Override this to fine-tune serialization for your persistence.
     *
     * @param Field $f
     * @param mixed $value
     *
     * @return mixed
     */
    public function _serializeSaveField(Field $f, $value)
    {
        switch ($f->serialize === true ? 'serialize' : $f->serialize) {
        case 'serialize':
            return serialize($value);
        case 'json':
            return json_encode($value);
        case 'base64':
            if (!is_string($value)) {
                throw new Exception([
                    'Field value can not be base64 encoded because it is not of string type',
                    'field' => $f,
                    'value' => $value,
                ]);
            }

            return base64_encode($value);
        }
    }

    /**
     * Override this to fine-tune un-serialization for your persistence.
     *
     * @param Field $f
     * @param mixed $value
     *
     * @return mixed
     */
    public function _serializeLoadField(Field $f, $value)
    {
        switch ($f->serialize === true ? 'serialize' : $f->serialize) {
        case 'serialize':
            return unserialize($value);
        case 'json':
            switch ($f->type) {
            case 'array':
                return json_decode($value, true);
            case 'object':
                return json_decode($value, false);
            }

            return json_decode($value, true);
        case 'base64':
            return base64_decode($value);
        }
    }
}
