<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Persistence class.
 */
class Persistence
{
    use \atk4\core\ContainerTrait {
        add as _add;
    }
    use \atk4\core\FactoryTrait;
    use \atk4\core\HookTrait;
    use \atk4\core\AppScopeTrait;
    use \atk4\core\DynamicMethodTrait;
    use \atk4\core\NameTrait;
    use \atk4\core\DIContainerTrait;

    /** @var string Connection driver name, for example, mysql, pgsql, oci etc. */
    public $driver;

    /**
     * Connects database.
     *
     * @param string $dsn      Format as PDO DSN or use "mysql://user:pass@host/db;option=blah", leaving user and password arguments = null
     * @param string $user
     * @param string $password
     * @param array  $args
     *
     * @throws Exception
     * @throws \atk4\dsql\Exception
     *
     * @return Persistence
     */
    public static function connect($dsn, $user = null, $password = null, $args = [])
    {
        // Process DSN string
        $dsn = \atk4\dsql\Connection::normalizeDSN($dsn, $user, $password);

        $driver = isset($args['driver']) ? strtolower($args['driver']) : $dsn['driver'];

        switch ($driver) {
            case 'mysql':
            case 'oci':
            case 'oci12':
                // Omitting UTF8 is always a bad problem, so unless it's specified we will do that
                // to prevent nasty problems. This is un-tested on other databases, so moving it here.
                // It gives problem with sqlite
                if (strpos($dsn['dsn'], ';charset=') === false) {
                    $dsn['dsn'] .= ';charset=utf8mb4';
                }

            case 'pgsql':
            case 'dumper':
            case 'counter':
            case 'sqlite':
                $db = new \atk4\data\Persistence\SQL($dsn['dsn'], $dsn['user'], $dsn['pass'], $args);
                $db->driver = $driver;

                return $db;
            default:
                throw new Exception([
                    'Unable to determine persistence driver from DSN',
                    'dsn' => $dsn['dsn'],
                ]);
        }
    }

    /**
     * Disconnect from database explicitly.
     */
    public function disconnect()
    {
    }

    /**
     * Associate model with the data driver.
     *
     * @param Model|string $m        Model which will use this persistence
     * @param array        $defaults Properties
     *
     * @throws Exception
     * @throws \atk4\core\Exception
     *
     * @return Model
     */
    public function add($m, $defaults = [])
    {
        /*
        if (isset($defaults[0])) {
            $m->table = $defaults[0];
            unset($defaults[0]);
        }
         */

        $m = $this->factory($m, $defaults);

        if ($m->persistence) {
            if ($m->persistence === $this) {
                return $m;
            }

            throw new Exception([
                'Model is already related to another persistence',
            ]);
        }

        $m->persistence = $this;
        $m->persistence_data = [];
        $this->initPersistence($m);
        $m = $this->_add($m);

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
     * @param callable $fx
     *
     * @return mixed
     */
    public function atomic($fx)
    {
        return call_user_func($fx);
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
     * @param Model $model
     * @param array $row
     *
     * @return array
     */
    public function typecastSaveRow(Model $model, $row)
    {
        if (!$row) {
            return $row;
        }

        $result = [];
        foreach ($row as $key => $value) {

            // Look up field object
            // If we have no knowledge of the field, it wasn't defined, so we will leave it as-is.
            if (!$field = $model->hasField($key)) {
                $result[$key] = $value;
                continue;
            }

            // check null values for mandatory fields
            if ($value === null && $field->mandatory) {
                throw new ValidationException([$key => 'Mandatory field value cannot be null']);
            }
            
            // Figure out the name of the destination field
            $key = empty($field->actual) ? $key : $field->actual;

            // Expression and null cannot be converted.
            if (
                $value instanceof \atk4\dsql\Expression ||
                $value instanceof \atk4\dsql\Expressionable ||
                $value === null
            ) {
                $result[$key] = $value;
                continue;
            }

            // typecast if we explicitly want that or there is not serialization enabled
            if ($field->typecast || ($field->typecast === null && $field->serialize === null)) {
                $value = $this->typecastSaveField($field, $value);
            }

            // serialize if we explicitly want that
            if ($field->serialize) {
                $value = $this->serializeSaveField($field, $value);
            }

            // store converted value
            $result[$key] = $value;
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
     * @param Model $model
     * @param array $row
     *
     * @return array
     */
    public function typecastLoadRow(Model $model, $row)
    {
        if (!$row) {
            return $row;
        }

        $result = [];
        foreach ($row as $key => &$value) {

            // Look up field object
            // If we have no knowledge of the field, it wasn't defined, so we will leave it as-is.
            if (!$field = $model->hasField($key)) {
                $result[$key] = $value;
                continue;
            }

            // ignore null values
            if ($value === null) {
                $result[$key] = $value;
                continue;
            }

            // serialize if we explicitly want that
            if ($field->serialize) {
                $value = $this->serializeLoadField($field, $value);
            }

            // typecast if we explicitly want that or there is not serialization enabled
            if ($field->typecast || ($field->typecast === null && $field->serialize === null)) {
                $value = $this->typecastLoadField($field, $value);
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
     * @param Field $field
     * @param mixed $value
     *
     * @return mixed
     */
    public function typecastSaveField(Field $field, $value)
    {
        try {
            if ($typecast = $field->getTypecaster('save')) {
                return $typecast($value, $field, $this);
            }

            // we respect null values
            if ($value === null) {
                return;
            }

            // run persistence-specific typecasting of field value
            return $this->_typecastSaveField($field, $value);
        } catch (\Exception $e) {
            throw new Exception(['Unable to typecast field value on save', 'field' => $field->name], null, $e);
        }
    }

    /**
     * Cast specific field value from the way how it's stored inside
     * persistence to a PHP format.
     *
     * @param Field $field
     * @param mixed $value
     *
     * @return mixed
     */
    public function typecastLoadField(Field $field, $value)
    {
        try {
            if ($typecast = $field->getTypecaster('load')) {
                return $typecast($value, $field, $this);
            }

            // only string type fields can use empty string as legit value, for all
            // other field types empty value is the same as no-value, nothing or null
            if ($field->type && $field->type != 'string' && $value === '') {
                return;
            }

            // we respect null values
            if ($value === null) {
                return;
            }

            // run persistence-specific typecasting of field value
            return $this->_typecastLoadField($field, $value);
        } catch (\Exception $e) {
            throw new Exception(['Unable to typecast field value on load', 'field' => $field->name], null, $e);
        }
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
     * @param Field $field
     * @param mixed $value
     *
     * @return mixed
     */
    public function serializeSaveField(Field $field, $value)
    {
        try {
            if ($encode = $field->getSerializer('encode')) {
                return $encode($field, $value, $this);
            }

            // run persistence-specific serialization of field value
            return $this->_serializeSaveField($field, $value);
        } catch (\Exception $e) {
            throw new Exception(['Unable to serialize field value on save', 'field' => $field->name], null, $e);
        }
    }

    /**
     * Provided with a value, will perform field un-serialization.
     * Can be used for the purposes of encryption or storing unsupported formats.
     *
     * @param Field $field
     * @param mixed $value
     *
     * @return mixed
     */
    public function serializeLoadField(Field $field, $value)
    {
        try {
            if ($decode = $field->getSerializer('decode')) {
                return $decode($field, $value, $this);
            }

            // run persistence-specific un-serialization of field value
            return $this->_serializeLoadField($field, $value);
        } catch (\Exception $e) {
            throw new Exception(['Unable to serialize field value on load', 'field' => $field->name], null, $e);
        }
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
            return $this->jsonEncode($f, $value);
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
            return $this->jsonDecode($f, $value, $f->type == 'array');
        case 'base64':
            return base64_decode($value);
        }
    }

    /**
     * JSON decoding with proper error treatment.
     *
     * @param Field  $f
     * @param string $value
     * @param bool   $assoc
     *
     * @return mixed
     */
    public function jsonDecode(Field $f, $value, $assoc = true)
    {
        // constant supported only starting PHP 7.3
        if (!defined('JSON_THROW_ON_ERROR')) {
            define('JSON_THROW_ON_ERROR', 0);
        }

        $res = json_decode($value, $assoc, 512, JSON_THROW_ON_ERROR);
        if (JSON_THROW_ON_ERROR == 0 && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception([
                'There was error while decoding JSON',
                'code'  => json_last_error(),
                'error' => json_last_error_msg(),
            ]);
        }

        return $res;
    }

    /**
     * JSON encoding with proper error treatment.
     *
     * @param Field $f
     * @param mixed $value
     *
     * @return string
     */
    public function jsonEncode(Field $f, $value)
    {
        // constant supported only starting PHP 7.3
        if (!defined('JSON_THROW_ON_ERROR')) {
            define('JSON_THROW_ON_ERROR', 0);
        }

        $res = json_encode($value, JSON_THROW_ON_ERROR, 512);
        if (JSON_THROW_ON_ERROR == 0 && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception([
                'There was error while encoding JSON',
                'code'  => json_last_error(),
                'error' => json_last_error_msg(),
            ]);
        }

        return $res;
    }
}
