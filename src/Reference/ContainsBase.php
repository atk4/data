<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql\Mysql\Connection as MysqlConnection;
use Atk4\Data\Reference;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

abstract class ContainsBase extends Reference
{
    protected const HOOK_PRIORITY_EARLY = -500;
    protected const HOOK_PRIORITY_LATE = 500;

    public bool $checkTheirType = false;

    /** Field type. */
    public string $type = 'json';

    /** Is it system field? */
    public bool $system = true;

    /** @var array<string, mixed> Array with UI flags like editable, visible and hidden. */
    public array $ui = [];

    /** @var string Required! We need table alias for internal use only. */
    protected $tableAlias = 'tbl';

    #[\Override]
    protected function init(): void
    {
        parent::init();

        if ($this->ourField === null) {
            $this->ourField = $this->link;
        }

        $ourModel = $this->getOurModel();

        $ourField = $this->getOurFieldName();
        if (!$ourModel->hasField($ourField)) {
            $ourModel->addField($ourField, [
                'type' => $this->type,
                'referenceLink' => $this->link,
                'system' => $this->system,
                'caption' => $this->caption, // it's reference models caption, but we can use it here for field too
                'ui' => array_merge([
                    'visible' => false, // not visible in UI Table, Grid and Crud
                    'editable' => true, // but should be editable in UI Form
                ], $this->ui),
            ]);
        }

        // prevent unmanaged data modification
        // https://github.com/atk4/data/issues/881
        $this->onHookToOurModel(Model::HOOK_NORMALIZE, function (Model $ourModel, Field $field, $value) {
            if (!$field->hasReference() || $field->shortName !== $this->getOurFieldName()) {
                return;
            }
            assert($field->getReference() === $this);

            $calledFromModelSet = false;
            foreach (debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS | \DEBUG_BACKTRACE_PROVIDE_OBJECT) as $frame) {
                if (!$calledFromModelSet) {
                    if ($frame['function'] === 'set' && ($frame['object'] ?? null) instanceof Model && $frame['object']->getModel() === $this->getOurModel()) {
                        $calledFromModelSet = true;
                    }
                } else {
                    // allow save from ContainsOne/ContainsMany hooks
                    if (($frame['object'] ?? null) === $this) {
                        return;
                    }
                }
            }

            if ($calledFromModelSet) {
                throw new Exception('Contained model data cannot be modified directly');
            }
        }, [], \PHP_INT_MIN);

        // fix JSON object reordered by MySQL
        // https://bugs.mysql.com/bug.php?id=100974
        $ourMysqlConnection = $this->getOurModel()->getPersistence() instanceof Persistence\Sql && $this->getOurModel()->getPersistence()->getDatabasePlatform() instanceof AbstractMySQLPlatform
            ? $this->getOurModel()->getPersistence()->getConnection()
            : null;
        if ($ourMysqlConnection !== null && !MysqlConnection::isServerMariaDb($ourMysqlConnection)) {
            $this->onHookToOurModel(Model::HOOK_AFTER_LOAD, function (Model $ourEntity) {
                $value = &$ourEntity->getDataRef()[$this->getOurFieldName()];

                if ($value !== null) {
                    $theirModel = $this->createTheirModel();
                    $theirKeysOrder = array_flip(array_values(array_map(static fn ($v) => $v->getPersistenceName(), $theirModel->getFields())));

                    $reorderDataFx = static function (&$data) use ($theirKeysOrder) {
                        uksort($data, static fn ($a, $b) => ($theirKeysOrder[$a] ?? \PHP_INT_MAX) <=> ($theirKeysOrder[$b] ?? \PHP_INT_MAX));
                    };

                    if ($this->isOneToOne()) {
                        $reorderDataFx($value);
                    } else {
                        foreach (array_keys($value) as $k) {
                            $reorderDataFx($value[$k]);
                        }
                    }
                }
            }, [], self::HOOK_PRIORITY_EARLY);
        }

        // fix load by float value and dirty for MySQL v5.7.8 - v8.0.3
        // https://bugs.mysql.com/bug.php?id=88230
        if ($ourMysqlConnection !== null && !MysqlConnection::isServerMariaDb($ourMysqlConnection) && version_compare($ourMysqlConnection->getServerVersion(), '5.7.8') >= 0 && version_compare($ourMysqlConnection->getServerVersion(), '8.0.3') <= 0) {
            $this->onHookToOurModel(Model::HOOK_AFTER_LOAD, function (Model $ourEntity) {
                $value = &$ourEntity->getDataRef()[$this->getOurFieldName()];

                $theirModel = $this->createTheirModel();
                foreach ($this->isOneToOne() ? [$value] : ($value ?? []) as $i => $row) {
                    foreach ($theirModel->getFields() as $f) {
                        if ($f->type === 'float' || $f->type === 'atk4_money') {
                            $v = $row[$f->getPersistenceName()] ?? null;
                            if (is_int($v)) {
                                if ($this->isOneToOne()) {
                                    $value[$f->getPersistenceName()] = (float) $v;
                                } else {
                                    $value[$i][$f->getPersistenceName()] = (float) $v;
                                }
                            }
                        }
                    }
                }
            }, [], self::HOOK_PRIORITY_EARLY);
        }

        $this->onHookToOurModel(Model::HOOK_BEFORE_DELETE, function (Model $ourEntity) {
            $this->deleteTheirEntities($ourEntity);
        }, [], self::HOOK_PRIORITY_LATE);
    }

    #[\Override]
    protected function getDefaultPersistence(): Persistence
    {
        return new Persistence\Array_();
    }

    #[\Override]
    protected function createTheirModelBeforeInit(array $defaults): Model
    {
        $defaults['table'] = $this->tableAlias;

        $defaults['containedInPersistence'] ??= $this->getOwner()->containedInPersistence
            ?? $this->getOwner()->getPersistence();

        return parent::createTheirModelBeforeInit($defaults);
    }

    /**
     * @param array<int, mixed> $data
     */
    protected function setTheirModelPersistenceSeedData(Model $theirModel, array $data): void
    {
        $persistence = Persistence\Array_::assertInstanceOf($theirModel->getPersistence());
        $tableName = $this->tableAlias;
        \Closure::bind(static function () use ($persistence, $tableName, $data) {
            $persistence->seedData = [$tableName => $data];
            $persistence->data = [];
        }, null, Persistence\Array_::class)();
    }

    protected function deleteTheirEntities(Model $ourEntity): void
    {
        $theirModelOrEntity = $this->ref($ourEntity);

        if ($theirModelOrEntity->isEntity()) {
            if ($theirModelOrEntity->isLoaded()) {
                $theirModelOrEntity->delete();
            }

            return;
        }

        $theirModelOrEntity->atomic(static function () use ($theirModelOrEntity) {
            foreach ($theirModelOrEntity as $theirEntity) {
                $theirEntity->delete();
            }
        });
    }
}
