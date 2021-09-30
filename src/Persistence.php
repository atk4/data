<?php

declare(strict_types=1);

namespace Atk4\Data;

use Atk4\Core\Factory;
use Doctrine\DBAL\Platforms;

abstract class Persistence
{
    use \Atk4\Core\ContainerTrait {
        add as _add;
    }
    use \Atk4\Core\DiContainerTrait;
    use \Atk4\Core\DynamicMethodTrait;
    use \Atk4\Core\HookTrait;
    use \Atk4\Core\NameTrait;

    /** @const string */
    public const HOOK_AFTER_ADD = self::class . '@afterAdd';

    /** @const string */
    public const ID_LOAD_ONE = self::class . '@idLoadOne';
    /** @const string */
    public const ID_LOAD_ANY = self::class . '@idLoadAny';

    /**
     * Connects database.
     *
     * @param string|array $dsn Format as PDO DSN or use "mysql://user:pass@host/db;option=blah",
     *                          leaving user and password arguments = null
     */
    public static function connect($dsn, string $user = null, string $password = null, array $args = []): self
    {
        // parse DSN string
        $dsn = \Atk4\Data\Persistence\Sql\Connection::normalizeDsn($dsn, $user, $password);

        switch ($dsn['driverSchema']) {
            case 'mysql':
            case 'oci':
            case 'oci12':
                // Omitting UTF8 is always a bad problem, so unless it's specified we will do that
                // to prevent nasty problems. This is un-tested on other databases, so moving it here.
                // It gives problem with sqlite
                if (strpos($dsn['dsn'], ';charset=') === false) {
                    $dsn['dsn'] .= ';charset=utf8mb4';
                }

                // no break
            case 'pgsql':
            case 'sqlsrv':
            case 'sqlite':
                $db = new \Atk4\Data\Persistence\Sql($dsn['dsn'], $dsn['user'], $dsn['pass'], $args);

                return $db;
            default:
                throw (new Exception('Unable to determine persistence driver type from DSN'))
                    ->addMoreInfo('dsn', $dsn['dsn']);
        }
    }

    /**
     * Disconnect from database explicitly.
     */
    public function disconnect(): void
    {
    }

    /**
     * Associate model with the data driver.
     */
    public function add(Model $m, array $defaults = []): Model
    {
        $m = Factory::factory($m, $defaults);

        if ($m->persistence) {
            if ($m->persistence === $this) {
                return $m;
            }

            throw new Exception('Model is already related to another persistence');
        }

        $m->persistence = $this;
        $m->persistence_data = [];
        $this->initPersistence($m);
        $m = $this->_add($m);

        $this->hook(self::HOOK_AFTER_ADD, [$m]);

        return $m;
    }

    /**
     * Extend this method to enhance model to work with your persistence. Here
     * you can define additional methods or store additional data. This method
     * is executed before model's init().
     */
    protected function initPersistence(Model $m): void
    {
    }

    /**
     * Atomic executes operations within one begin/end transaction. Not all
     * persistences will support atomic operations, so by default we just
     * don't do anything.
     *
     * @return mixed
     */
    public function atomic(\Closure $fx)
    {
        return $fx();
    }

    public function getDatabasePlatform(): Platforms\AbstractPlatform
    {
        return new Persistence\GenericPlatform();
    }

    /**
     * Tries to load data record, but will not fail if record can't be loaded.
     *
     * @param mixed $id
     */
    public function tryLoad(Model $model, $id): ?array
    {
        throw new Exception('Load is not supported.');
    }

    /**
     * Loads a record from model and returns a associative array.
     *
     * @param mixed $id
     */
    public function load(Model $model, $id): array
    {
        $data = $this->tryLoad(
            $model,
            $id,
            ...array_slice(func_get_args(), 2, null, true)
        );

        if (!$data) {
            $noId = $id === self::ID_LOAD_ONE || $id === self::ID_LOAD_ANY;

            throw (new Exception($noId ? 'No record was found' : 'Record with specified ID was not found', 404))
                ->addMoreInfo('model', $model)
                ->addMoreInfo('id', $noId ? null : $id)
                ->addMoreInfo('scope', $model->getModel(true)->scope()->toWords());
        }

        return $data;
    }

    /**
     * Will convert one row of data from native PHP types into
     * persistence types. This will also take care of the "actual"
     * field keys. Example:.
     *
     * In:
     *  [
     *    'name' => ' John Smith',
     *    'age' => 30,
     *    'password' => 'abc',
     *    'is_married' => true,
     *  ]
     *
     *  Out:
     *   [
     *     'first_name' => 'John Smith',
     *     'age' => 30,
     *     'is_married' => 1
     *   ]
     */
    public function typecastSaveRow(Model $model, array $row): array
    {
        $result = [];
        foreach ($row as $fieldName => $value) {
            // We have no knowledge of the field, it wasn't defined, so
            // we will leave it as-is.
            if (!$model->hasField($fieldName)) {
                $result[$fieldName] = $value;

                continue;
            }

            // Look up field object
            $field = $model->getField($fieldName);

            // check null values for mandatory fields
            if ($value === null && $field->mandatory) {
                throw new ValidationException([$fieldName => 'Mandatory field value cannot be null'], $model);
            }

            // Expression and null cannot be converted.
            if (
                $value instanceof \Atk4\Data\Persistence\Sql\Expression
                || $value instanceof \Atk4\Data\Persistence\Sql\Expressionable
                || $value === null
            ) {
                $result[$field->getPersistenceName()] = $value;

                continue;
            }

            $value = $this->typecastSaveField($field, $value);

            // store converted value
            $result[$field->getPersistenceName()] = $value;
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
     */
    public function typecastLoadRow(Model $model, array $row): array
    {
        $result = [];
        foreach ($row as $fieldName => $value) {
            // We have no knowledge of the field, it wasn't defined, so
            // we will leave it as-is.
            if (!$model->hasField($fieldName)) {
                $result[$fieldName] = $value;

                continue;
            }

            // Look up field object
            $field = $model->getField($fieldName);

            // ignore null values
            if ($value === null) {
                $result[$fieldName] = $value;

                continue;
            }

            $value = $this->typecastLoadField($field, $value);

            // store converted value
            $result[$fieldName] = $value;
        }

        return $result;
    }

    /**
     * Prepare value of a specific field by converting it to
     * persistence-friendly format.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function typecastSaveField(Field $f, $value)
    {
        try {
            // we respect null values
            if ($value === null) {
                return;
            }

            // run persistence-specific typecasting of field value
            return $this->_typecastSaveField($f, $value);
        } catch (\Exception $e) {
            throw (new Exception('Unable to typecast field value on save', 0, $e))
                ->addMoreInfo('field', $f->short_name);
        }
    }

    /**
     * Cast specific field value from the way how it's stored inside
     * persistence to a PHP format.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function typecastLoadField(Field $f, $value)
    {
        try {
            // only string type fields can use empty string as legit value, for all
            // other field types empty value is the same as no-value, nothing or null
            if ($f->type && $f->type !== 'string' && $value === '') {
                return;
            }

            // we respect null values
            if ($value === null) {
                return;
            }

            // run persistence-specific typecasting of field value
            return $this->_typecastLoadField($f, $value);
        } catch (\Exception $e) {
            throw (new Exception('Unable to typecast field value on load', 0, $e))
                ->addMoreInfo('field', $f->short_name);
        }
    }

    /**
     * This is the actual field typecasting, which you can override in your
     * persistence to implement necessary typecasting.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastSaveField(Field $f, $value)
    {
        return $f->getTypeObject()->convertToDatabaseValue($value, $this->getDatabasePlatform());
    }

    /**
     * This is the actual field typecasting, which you can override in your
     * persistence to implement necessary typecasting.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastLoadField(Field $f, $value)
    {
        return $f->getTypeObject()->convertToPHPValue($value, $this->getDatabasePlatform());
    }
}
