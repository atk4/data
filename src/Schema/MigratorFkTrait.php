<?php

declare(strict_types=1);

namespace Atk4\Data\Schema;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Model\Join;
use Atk4\Data\Persistence;
use Atk4\Data\Reference;
use Atk4\Data\Reference\ContainsBase;
use Atk4\Data\Reference\HasOne;

trait MigratorFkTrait
{
    public function createDummyRelation(string $localTable, string $localColumn, string $foreignTable, string $foreignColumn): HasOne
    {
        $dummyPersistence = new Persistence\Array_();
        $dummyLocalModel = new Model($dummyPersistence, ['table' => $localTable]);
        $dummyForeignModel = new Model($dummyPersistence, ['table' => $foreignTable]);
        if ($foreignColumn !== 'id') {
            $dummyForeignModel->addField($foreignColumn);
        }
        $ref = $dummyLocalModel->hasOne('x', [
            'model' => $dummyForeignModel,
            'our_field' => $localColumn,
            'their_field' => $foreignColumn,
        ]);

        return $ref;
    }

    public function debugSetupForeignKeysFromModel(Model $model): void
    {
        if ($model->isEntity()) {
            $model = $model->getModel();
        }

        if (!$model->issetPersistence() || !$model->getPersistence() instanceof Persistence\Sql) {
            return;
        }

        $test = TestCase::getTestFromBacktrace();
        if ($test->toString() === \Atk4\Data\Tests\Schema\TestCaseTest::class . '::testLogQuery') { // discovery queries are breaking the test
            return;
        } elseif (preg_match('~\\\\JoinSqlTest::(testJoinDelete|testDoubleJoin|testDoubleReverseJoin)$~', $test->toString())) { // Join delete is broken and even 1:N records are deleted
            return;
        } elseif (str_contains($test->toString(), \Atk4\Data\Tests\Util\DeepCopyTest::class . '::')) { // DcInvoiceLine and DcQuoteLine models use the same table but "parent_id" links to a different tables
            return;
        }

        if ($model->getPersistence()->getConnection()->inTransaction()) {
            // DB table/keys cannot be altered inside transaction, so postpone the update
            $getDbFx = function (): TestSqlPersistence {
                return TestCase::getTestFromBacktrace()->db; // @phpstan-ignore-line
            };
            $getDbFx()->afterTransactionCallbacks[spl_object_id($model)] = function () use ($getDbFx, $model) {
                $getDbFx()->setupforeignKeysFromModel($model);
            };

            return;
        }

        foreach ($model->getRefs() as $ref) {
            // ContainsSeedHackTrait requires special calling stack for createTheirModel
            if ($ref instanceof ContainsBase) {
                continue;
            }

            $this->debugCreateForeignKey($ref);
        }

        // TODO Model::getJoins() method should be probably added (and only into Model, elsewhere does not make sense and getJoin is enough)
        foreach ($model->elements as $join) {
            if ($join instanceof Join) {
                $this->debugCreateForeignKey($join);
            }
        }

        // DEBUG test if models never access unavailable fields and if our assumptions
        // about the data structures are correct
        foreach ($model->getFields() as $field) {
            $field = $this->resolvePersistenceField($field);

            // TODO read_only is set by SqlExpressionField, but what is the difference vs. never_save?
            // And is it tested everywhere corretly when never_persist is set?
            // NOTE: never_persist is used in once in Atk4\Data\Tests\Model\Smbo\Transfer model
            if ($field !== null /* implies never_persists !== true */ && !$field->read_only && !$field->never_save) {
                $this->debugCreateForeignKey($this->createDummyRelation(
                    $field->getOwner()->table,
                    $field->getPersistenceName(),
                    $field->getOwner()->table,
                    $field->getPersistenceName()
                ));
            }
        }
    }

    /**
     * @param Reference|Join $relation
     */
    protected function debugCreateForeignKey(object $relation): void
    {
        [$localField, $foreignField] = $this->resolveRelationDirection($relation);
        $localField = $this->resolvePersistenceField($localField);
        $foreignField = $this->resolvePersistenceField($foreignField);

        TestCase::getTestFromBacktrace()->db->setupforeignKeysFromModel($localField->getOwner());
        TestCase::getTestFromBacktrace()->db->setupforeignKeysFromModel($foreignField->getOwner());

        if ($localField === null || $foreignField === null) {
            return;
        }

        if (!$this->isTableExists($localField->getOwner()->table)) {
            // in theory, any name can not exist, but we know the failing set,
            // so we validate it, to prevent wrong names and skipping FK completely
            if (in_array($localField->getOwner()->table, ['user', 'ticket', 'order', 'currency', 'role'], true) || preg_match('~\.(user|doc)$~', $localField->getOwner()->table)) {
                return;
            }

            throw new Exception('Unexpected non-existing foreign table: ' . $localField->getOwner()->table);
        }
        if (!$this->isTableExists($foreignField->getOwner()->table)) {
            if (in_array($foreignField->getOwner()->table, ['role'], true)) {
                return;
            }

            throw new Exception('Unexpected non-existing target table: ' . $foreignField->getOwner()->table);
        }

        if ($localField->getOwner()->table === $foreignField->getOwner()->table && $localField->getPersistenceName() === $foreignField->getPersistenceName()) {
            return;
        }

        if ($this->debugWasFkAlreadyAdded($localField, $foreignField)) {
            // echo 'FK already added ' . $localField->getOwner()->table . '.' . $localField->getPersistenceName() . ' to ' . $foreignField->getOwner()->table . '.' . $foreignField->getPersistenceName() . "\n";

            return;
        }

        // echo 'adding FK from ' . $localField->getOwner()->table . '.' . $localField->getPersistenceName() . ' to ' . $foreignField->getOwner()->table . '.' . $foreignField->getPersistenceName() . "\n";

        $this->createForeignKey([$localField, $foreignField]);
    }

    private function debugWasFkAlreadyAdded(Field $localField, Field $foreignField): bool
    {
        $foreignKeys = $this->createSchemaManager()->listTableForeignKeys($this->fixTableNameForListMethod($localField->getOwner()->table));
        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey->getUnquotedLocalColumns() === [$localField->getPersistenceName()]
                && $foreignKey->getForeignTableName() === $this->stripDatabaseFromTableName($foreignField->getOwner()->table)
                && $foreignKey->getUnquotedForeignColumns() === [$foreignField->getPersistenceName()]
            ) {
                return true;
            }
        }

        return false;
    }
}
