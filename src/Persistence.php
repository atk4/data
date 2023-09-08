<?php

declare(strict_types=1);

namespace Atk4\Data;

use Atk4\Core\ContainerTrait;
use Atk4\Core\DiContainerTrait;
use Atk4\Core\DynamicMethodTrait;
use Atk4\Core\Factory;
use Atk4\Core\HookTrait;
use Atk4\Core\NameTrait;
use Doctrine\DBAL\Platforms;
use Doctrine\DBAL\Types\Type;

abstract class Persistence
{
    use ContainerTrait {
        add as private _add;
    }
    use DiContainerTrait;
    use DynamicMethodTrait;
    use HookTrait;
    use NameTrait;

    public const HOOK_AFTER_ADD = self::class . '@afterAdd';

    public const ID_LOAD_ONE = self::class . '@idLoadOne-qZ5TJwMVJ4LzVhuN';
    public const ID_LOAD_ANY = self::class . '@idLoadAny-qZ5TJwMVJ4LzVhuN';

    /** @internal prevent recursion */
    private bool $typecastSaveSkipNormalize = false;

    /**
     * Connects database.
     *
     * @param string|array<string, string> $dsn      Format as PDO DSN or use "mysql://user:pass@host/db;option=blah",
     *                                               leaving user and password arguments = null
     * @param array<string, mixed>         $defaults
     */
    public static function connect($dsn, string $user = null, string $password = null, array $defaults = []): self
    {
        // parse DSN string
        $dsn = Persistence\Sql\Connection::normalizeDsn($dsn, $user, $password);

        switch ($dsn['driver']) {
            case 'pdo_sqlite':
            case 'pdo_mysql':
            case 'mysqli':
            case 'pdo_pgsql':
            case 'pdo_sqlsrv':
            case 'pdo_oci':
            case 'oci8':
                $persistence = new Persistence\Sql($dsn, $dsn['user'] ?? null, $dsn['password'] ?? null, $defaults);

                return $persistence;
            default:
                throw (new Exception('Unable to determine persistence driver type'))
                    ->addMoreInfo('dsn', $dsn);
        }
    }

    /**
     * Disconnect from database explicitly.
     */
    public function disconnect(): void {}

    /**
     * Associate model with the data driver.
     *
     * @param array<string, mixed> $defaults
     */
    public function add(Model $model, array $defaults = []): void
    {
        if ($model->issetPersistence() || $model->persistenceData !== []) {
            throw new \Error('Persistence::add() cannot be called directly, use Model::setPersistence() instead');
        }

        Factory::factory($model, $defaults);
        $this->initPersistence($model);
        $model->setPersistence($this);

        // invokes Model::init()
        // model is not added to elements as it does not implement TrackableTrait trait
        $this->_add($model);

        $this->hook(self::HOOK_AFTER_ADD, [$model]);
    }

    /**
     * Extend this method to enhance model to work with your persistence. Here
     * you can define additional methods or store additional data. This method
     * is executed before model's init().
     */
    protected function initPersistence(Model $m): void {}

    /**
     * Atomic executes operations within one begin/end transaction. Not all
     * persistencies will support atomic operations, so by default we just
     * don't do anything.
     *
     * @template T
     *
     * @param \Closure(): T $fx
     *
     * @return T
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
     *
     * @return array<string, mixed>|null
     */
    public function tryLoad(Model $model, $id): ?array
    {
        throw new Exception('Load is not supported');
    }

    /**
     * Loads a record from model and returns a associative array.
     *
     * @param mixed $id
     *
     * @return array<string, mixed>
     */
    public function load(Model $model, $id): array
    {
        $model->assertIsModel();

        $data = $this->tryLoad($model, $id);

        if (!$data) {
            $noId = $id === self::ID_LOAD_ONE || $id === self::ID_LOAD_ANY;

            throw (new Exception($noId ? 'No record was found' : 'Record with specified ID was not found'))
                ->addMoreInfo('model', $model)
                ->addMoreInfo('id', $noId ? null : $id)
                ->addMoreInfo('scope', $model->scope()->toWords());
        }

        return $data;
    }

    /**
     * Inserts record in database and returns new record ID.
     *
     * @param array<string, mixed> $data
     *
     * @return mixed
     */
    public function insert(Model $model, array $data)
    {
        $model->assertIsModel();

        if ($model->idField && array_key_exists($model->idField, $data) && $data[$model->idField] === null) {
            unset($data[$model->idField]);
        }

        $dataRaw = $this->typecastSaveRow($model, $data);
        unset($data);

        if (is_object($model->table)) {
            $innerInsertId = $model->table->insert($this->typecastLoadRow($model->table, $dataRaw));
            if (!$model->idField) {
                return false;
            }

            $idField = $model->getField($model->idField);
            $insertId = $this->typecastLoadField(
                $idField,
                $idField->getPersistenceName() === $model->table->idField
                    ? $this->typecastSaveField($model->table->getField($model->table->idField), $innerInsertId)
                    : $dataRaw[$idField->getPersistenceName()]
            );

            return $insertId;
        }

        $idRaw = $this->insertRaw($model, $dataRaw);
        if (!$model->idField) {
            return false;
        }

        $id = $this->typecastLoadField($model->getField($model->idField), $idRaw);

        return $id;
    }

    /**
     * @param array<scalar|null> $dataRaw
     *
     * @return mixed
     */
    protected function insertRaw(Model $model, array $dataRaw)
    {
        throw new Exception('Insert is not supported');
    }

    /**
     * Updates record in database.
     *
     * @param mixed                $id
     * @param array<string, mixed> $data
     */
    public function update(Model $model, $id, array $data): void
    {
        $model->assertIsModel();

        $idRaw = $model->idField ? $this->typecastSaveField($model->getField($model->idField), $id) : null;
        unset($id);
        if ($idRaw === null || (array_key_exists($model->idField, $data) && $data[$model->idField] === null)) {
            throw new Exception('Unable to update record: Model idField is not set');
        }

        $dataRaw = $this->typecastSaveRow($model, $data);
        unset($data);

        if (count($dataRaw) === 0) {
            return;
        }

        if (is_object($model->table)) {
            $idPersistenceName = $model->getField($model->idField)->getPersistenceName();
            $innerId = $this->typecastLoadField($model->table->getField($idPersistenceName), $idRaw);
            $innerEntity = $model->table->loadBy($idPersistenceName, $innerId);

            $innerEntity->saveAndUnload($this->typecastLoadRow($model->table, $dataRaw));

            return;
        }

        $this->updateRaw($model, $idRaw, $dataRaw);
    }

    /**
     * @param mixed              $idRaw
     * @param array<scalar|null> $dataRaw
     */
    protected function updateRaw(Model $model, $idRaw, array $dataRaw): void
    {
        throw new Exception('Update is not supported');
    }

    /**
     * Deletes record from database.
     *
     * @param mixed $id
     */
    public function delete(Model $model, $id): void
    {
        $model->assertIsModel();

        $idRaw = $model->idField ? $this->typecastSaveField($model->getField($model->idField), $id) : null;
        unset($id);
        if ($idRaw === null) {
            throw new Exception('Unable to delete record: Model idField is not set');
        }

        if (is_object($model->table)) {
            $idPersistenceName = $model->getField($model->idField)->getPersistenceName();
            $innerId = $this->typecastLoadField($model->table->getField($idPersistenceName), $idRaw);
            $innerEntity = $model->table->loadBy($idPersistenceName, $innerId);

            $innerEntity->delete();

            return;
        }

        $this->deleteRaw($model, $idRaw);
    }

    /**
     * @param mixed $idRaw
     */
    protected function deleteRaw(Model $model, $idRaw): void
    {
        throw new Exception('Delete is not supported');
    }

    /**
     * Will convert one row of data from native PHP types into
     * persistence types. This will also take care of the "actual"
     * field keys.
     *
     * @param array<string, mixed> $row
     *
     * @return array<scalar|Persistence\Sql\Expressionable|null>
     */
    public function typecastSaveRow(Model $model, array $row): array
    {
        $result = [];
        foreach ($row as $fieldName => $value) {
            $field = $model->getField($fieldName);

            $result[$field->getPersistenceName()] = $this->typecastSaveField($field, $value);
        }

        return $result;
    }

    /**
     * Will convert one row of data from Persistence-specific
     * types to PHP native types.
     *
     * NOTE: Please DO NOT perform "actual" field mapping here, because data
     * may be "aliased" from SQL persistencies or mapped depending on persistence
     * driver.
     *
     * @param array<string, scalar|null> $row
     *
     * @return array<string, mixed>
     */
    public function typecastLoadRow(Model $model, array $row): array
    {
        $result = [];
        foreach ($row as $fieldName => $value) {
            $field = $model->getField($fieldName);

            $result[$fieldName] = $this->typecastLoadField($field, $value);
        }

        return $result;
    }

    /**
     * Prepare value of a specific field by converting it to
     * persistence-friendly format.
     *
     * @param mixed $value
     *
     * @return scalar|Persistence\Sql\Expressionable|null
     */
    public function typecastSaveField(Field $field, $value)
    {
        // SQL Expression cannot be converted
        if ($value instanceof Persistence\Sql\Expressionable) {
            return $value;
        }

        if (!$this->typecastSaveSkipNormalize) {
            $value = $field->normalize($value);
        }

        if ($value === null) {
            return null;
        }

        try {
            $v = $this->_typecastSaveField($field, $value);
            if ($v !== null && !is_scalar($v)) { // @phpstan-ignore-line
                throw new Exception('Unexpected non-scalar value');
            }

            return $v;
        } catch (\Exception $e) {
            throw (new Exception('Typecast save error', 0, $e))
                ->addMoreInfo('field', $field->shortName);
        }
    }

    /**
     * Cast specific field value from the way how it's stored inside
     * persistence to a PHP format.
     *
     * @param scalar|null $value
     *
     * @return mixed
     */
    public function typecastLoadField(Field $field, $value)
    {
        if ($value === null) {
            return null;
        } elseif (!is_scalar($value)) { // @phpstan-ignore-line
            throw new Exception('Unexpected non-scalar value');
        }

        try {
            return $this->_typecastLoadField($field, $value);
        } catch (\Exception $e) {
            throw (new Exception('Typecast parse error', 0, $e))
                ->addMoreInfo('field', $field->shortName);
        }
    }

    /**
     * This is the actual field typecasting, which you can override in your
     * persistence to implement necessary typecasting.
     *
     * @param mixed $value
     *
     * @return scalar|null
     */
    protected function _typecastSaveField(Field $field, $value)
    {
        if (in_array($field->type, ['json', 'object'], true) && $value === '') { // TODO remove later
            return null;
        }

        // native DBAL DT types have no microseconds support
        if (in_array($field->type, ['datetime', 'date', 'time'], true)
            && str_starts_with(get_class(Type::getType($field->type)), 'Doctrine\DBAL\Types\\')) {
            if ($value === '') {
                return null;
            } elseif (!$value instanceof \DateTimeInterface) {
                throw new Exception('Must be instance of DateTimeInterface');
            }

            if ($field->type === 'datetime') {
                $value = new \DateTime($value->format('Y-m-d H:i:s.u'), $value->getTimezone());
                $value->setTimezone(new \DateTimeZone('UTC'));
            }

            $format = ['date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s.u', 'time' => 'H:i:s.u'][$field->type];
            $value = $value->format($format);

            return $value;
        }

        $res = Type::getType($field->type)->convertToDatabaseValue($value, $this->getDatabasePlatform());
        if (is_resource($res) && get_resource_type($res) === 'stream') {
            $res = stream_get_contents($res);
        }

        return $res;
    }

    /**
     * This is the actual field typecasting, which you can override in your
     * persistence to implement necessary typecasting.
     *
     * @param scalar $value
     *
     * @return mixed
     */
    protected function _typecastLoadField(Field $field, $value)
    {
        // TODO casting optionally to null should be handled by type itself solely
        if ($value === '' && in_array($field->type, ['boolean', 'integer', 'float', 'datetime', 'date', 'time', 'json', 'object'], true)) {
            return null;
        }

        // native DBAL DT types have no microseconds support
        if (in_array($field->type, ['datetime', 'date', 'time'], true)
            && str_starts_with(get_class(Type::getType($field->type)), 'Doctrine\DBAL\Types\\')) {
            $format = ['date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s', 'time' => 'H:i:s'][$field->type];
            if (str_contains($value, '.')) { // time possibly with microseconds, otherwise invalid format
                $format = preg_replace('~(?<=H:i:s)(?![. ]*u)~', '.u', $format);
            }

            $valueOrig = $value;
            $value = \DateTime::createFromFormat('!' . $format, $value, new \DateTimeZone('UTC'));
            if ($value === false) {
                throw (new Exception('Incorrectly formatted datetime'))
                    ->addMoreInfo('format', $format)
                    ->addMoreInfo('value', $valueOrig)
                    ->addMoreInfo('field', $field);
            }

            if ($field->type === 'datetime') {
                $value->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            }

            return $value;
        }

        $res = Type::getType($field->type)->convertToPHPValue($value, $this->getDatabasePlatform());
        if (is_resource($res) && get_resource_type($res) === 'stream') {
            $res = stream_get_contents($res);
        }

        return $res;
    }
}
