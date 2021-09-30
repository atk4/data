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
    public function _typecastSaveField(Field $field, $value)
    {
        // work only on copied value not real one !!!
        $v = is_object($value) ? clone $value : $value;

        switch ($field->type) {
            case 'boolean':
                // if enum is not set, then simply cast value to boolean
                if (!isset($field->enum) || !$field->enum) {
                    $v = (bool) $v;

                    break;
                }

                // if enum is set, first lets see if it matches one of those precisely
                if ($v === $field->enum[1]) {
                    $v = true;
                } elseif ($v === $field->enum[0]) {
                    $v = false;
                }

                // finally, convert into appropriate value
                $v = $v ? $field->enum[1] : $field->enum[0];

                break;
            case 'date':
            case 'datetime':
            case 'time':
                $dt_class = \DateTime::class;
                $tz_class = \DateTimeZone::class;

                if ($v instanceof $dt_class || $v instanceof \DateTimeInterface) {
                    $format = ['date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s.u', 'time' => 'H:i:s.u'];
                    $format = $field->persist_format ?: $format[$field->type];

                    // datetime only - set to persisting timezone
                    if ($field->type === 'datetime' && isset($field->persist_timezone)) {
                        $v = new \DateTime($v->format('Y-m-d H:i:s.u'), $v->getTimezone());
                        $v->setTimezone(new $tz_class($field->persist_timezone));
                    }
                    $v = $v->format($format);
                }

                break;
            case 'array':
            case 'object':
            case 'json':
                $v = $field->getTypeObject()->convertToDatabaseValue($value, $this->getDatabasePlatform()); // TODO typecast everything, not only this type

                break;
        }

        return $v;
    }

    /**
     * This is the actual field typecasting, which you can override in your
     * persistence to implement necessary typecasting.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastLoadField(Field $field, $value)
    {
        // work only on copied value not real one !!!
        $v = is_object($value) ? clone $value : $value;

        switch ($field->type) {
            case 'string':
            case 'text':
                // do nothing - it's ok as it is
                break;
            case 'integer':
                $v = (int) $v;

                break;
            case 'float':
                $v = (float) $v;

                break;
            case 'money':
                $v = round((float) $v, 4);

                break;
            case 'boolean':
                if (is_array($field->enum ?? null)) {
                    if (isset($field->enum[0]) && $v === $field->enum[0]) {
                        $v = false;
                    } elseif (isset($field->enum[1]) && $v === $field->enum[1]) {
                        $v = true;
                    } else {
                        $v = null;
                    }
                } elseif ($v === '') {
                    $v = null;
                } else {
                    $v = (bool) $v;
                }

                break;
            case 'date':
            case 'datetime':
            case 'time':
                $dt_class = \DateTime::class;
                $tz_class = \DateTimeZone::class;

                if (is_numeric($v)) {
                    $v = new $dt_class('@' . $v);
                } elseif (is_string($v)) {
                    // ! symbol in date format is essential here to remove time part of DateTime - don't remove, this is not a bug
                    $format = ['date' => '+!Y-m-d', 'datetime' => '+!Y-m-d H:i:s', 'time' => '+!H:i:s'];
                    if ($field->persist_format) {
                        $format = $field->persist_format;
                    } else {
                        $format = $format[$field->type];
                        if (strpos($v, '.') !== false) { // time possibly with microseconds, otherwise invalid format
                            $format = preg_replace('~(?<=H:i:s)(?![. ]*u)~', '.u', $format);
                        }
                    }

                    // datetime only - set from persisting timezone
                    if ($field->type === 'datetime' && isset($field->persist_timezone)) {
                        $v = $dt_class::createFromFormat($format, $v, new $tz_class($field->persist_timezone));
                        if ($v !== false) {
                            $v->setTimezone(new $tz_class(date_default_timezone_get()));
                        }
                    } else {
                        $v = $dt_class::createFromFormat($format, $v);
                    }

                    if ($v === false) {
                        throw (new Exception('Incorrectly formatted date/time'))
                            ->addMoreInfo('format', $format)
                            ->addMoreInfo('value', $value)
                            ->addMoreInfo('field', $field);
                    }

                    // need to cast here because DateTime::createFromFormat returns DateTime object not $dt_class
                    // this is what Carbon::instance(DateTime $dt) method does for example
                    if ($dt_class !== \DateTime::class) { // @phpstan-ignore-line
                        $v = new $dt_class($v->format('Y-m-d H:i:s.u'), $v->getTimezone());
                    }
                }

                break;
            case 'array':
            case 'object':
            case 'json':
                $v = $field->getTypeObject()->convertToPHPValue($value, $this->getDatabasePlatform()); // TODO typecast everything, not only this type

                break;
        }

        return $v;
    }
}
