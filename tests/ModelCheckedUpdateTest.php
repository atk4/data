<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

class ModelCheckedUpdateTest extends TestCase
{
    protected function setupModelWithNameStartsWithJCondition(): Model
    {
        $model = new Model($this->db, ['table' => 't']);
        $model->addField('name');
        $this->createMigrator($model)->create();

        $model->import([
            ['name' => 'James'],
            ['name' => 'Roman'],
            ['name' => 'Jennifer'],
        ]);

        $model->addCondition('name', 'like', 'J%');

        return $model;
    }

    protected function addUniqueNameConditionToModel(Model $model): void
    {
        $modelInner = clone $model;
        $modelInner->tableAlias = 'ti';
        $q = $modelInner->action('count');
        $q->where('ti.name', $q->expr('{{}}', ['t.name']));
        $model->addCondition($q, '=', 1);
    }

    public function testInsertSimple(): void
    {
        $m = $this->setupModelWithNameStartsWithJCondition();

        $m->insert(['name' => 'John']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not found');
        try {
            $m->insert(['name' => 'Benjamin']);
        } finally {
            self::assertSameExportUnordered([
                ['id' => 1, 'name' => 'James'],
                ['id' => 3, 'name' => 'Jennifer'],
                ['id' => 4, 'name' => 'John'],
            ], $m->export());
        }
    }

    public function testInsertWithDependentCondition(): void
    {
        $m = $this->setupModelWithNameStartsWithJCondition();
        $this->addUniqueNameConditionToModel($m);

        $m->insert(['name' => 'John']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not found');
        try {
            $m->insert(['name' => 'John']);
        } finally {
            self::assertSameExportUnordered([
                ['id' => 1, 'name' => 'James'],
                ['id' => 3, 'name' => 'Jennifer'],
                ['id' => 4, 'name' => 'John'],
            ], $m->export());
        }
    }

    public function testUpdateSimple(): void
    {
        $m = $this->setupModelWithNameStartsWithJCondition();

        $entity3 = $m->load(3);
        $entity3->save(['name' => 'John']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not found');
        try {
            $entity3->save(['name' => 'Benjamin']);
        } finally {
            self::assertSameExportUnordered([
                ['id' => 1, 'name' => 'James'],
                ['id' => 3, 'name' => 'John'],
            ], $m->export());
        }
    }

    public function testUpdateWithDependentCondition(): void
    {
        $m = $this->setupModelWithNameStartsWithJCondition();
        $this->addUniqueNameConditionToModel($m);

        $entity3 = $m->load(3);
        $entity3->save(['name' => 'John']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not found');
        try {
            $entity3->save(['name' => 'James']);
        } finally {
            self::assertSameExportUnordered([
                ['id' => 1, 'name' => 'James'],
                ['id' => 3, 'name' => 'John'],
            ], $m->export());
        }
    }

    public function testUpdateWithConditionAddedAfterLoad(): void
    {
        $m = $this->setupModelWithNameStartsWithJCondition();

        $entity3 = $m->load(3);
        $entity3->save(['name' => 'John']);
        $m->addCondition('id', '<', 3);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not found');
        try {
            $entity3->save(['name' => 'Jan']);
        } finally {
            self::assertSameExportUnordered([
                ['id' => 1, 'name' => 'James'],
            ], $m->export());
            $m->scope()->clear();
            self::assertSameExportUnordered([
                ['id' => 1, 'name' => 'James'],
                ['id' => 2, 'name' => 'Roman'],
                ['id' => 3, 'name' => 'John'],
            ], $m->export());
        }
    }

    public function testUpdateUnconditioned(): void
    {
        $m = $this->setupModelWithNameStartsWithJCondition();

        $entity3 = $m->load(3);
        $entity3->onHook(Model::HOOK_BEFORE_UPDATE, static function (Model $entity) {
            (clone $entity)->delete();
            self::assertSameExportUnordered([
                ['id' => 1, 'name' => 'James'],
            ], $entity->getModel()->export());
            $entity->getModel()->scope()->clear();
        });

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Update failed, exactly 1 row was expected to be affected');
        try {
            $entity3->save(['name' => 'Jan']);
        } finally {
            self::assertSameExportUnordered([
                ['id' => 1, 'name' => 'James'],
                ['id' => 2, 'name' => 'Roman'],
                ['id' => 3, 'name' => 'Jennifer'],
            ], $m->export());
        }
    }

    public function testDeleteSimple(): void
    {
        $m = $this->setupModelWithNameStartsWithJCondition();

        $m->delete(3);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not found');
        try {
            $m->delete(3);
        } finally {
            self::assertSameExportUnordered([
                ['id' => 1, 'name' => 'James'],
            ], $m->export());
        }
    }

    public function testDeleteWithDependentCondition(): void
    {
        $m = $this->setupModelWithNameStartsWithJCondition();
        $this->addUniqueNameConditionToModel($m);

        $entity3 = $m->load(3);
        $m->load(3)->delete();
        self::assertTrue($entity3->isLoaded());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not found');
        try {
            $entity3->delete();
        } finally {
            self::assertSameExportUnordered([
                ['id' => 1, 'name' => 'James'],
            ], $m->export());
        }
    }

    public function testDeleteWithConditionAddedAfterLoad(): void
    {
        $m = $this->setupModelWithNameStartsWithJCondition();

        $entity3 = $m->load(3);
        $m->addCondition('id', '<', 3);
        self::assertTrue($entity3->isLoaded());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not found');
        try {
            $entity3->delete();
        } finally {
            self::assertSameExportUnordered([
                ['id' => 1, 'name' => 'James'],
            ], $m->export());
            $m->scope()->clear();
            self::assertSameExportUnordered([
                ['id' => 1, 'name' => 'James'],
                ['id' => 2, 'name' => 'Roman'],
                ['id' => 3, 'name' => 'Jennifer'],
            ], $m->export());
        }
    }

    public function testDeleteUnconditioned(): void
    {
        $m = $this->setupModelWithNameStartsWithJCondition();

        $entity3 = $m->load(3);
        $entity3->onHook(Model::HOOK_BEFORE_DELETE, static function (Model $entity) {
            (clone $entity)->delete();
            self::assertSameExportUnordered([
                ['id' => 1, 'name' => 'James'],
            ], $entity->getModel()->export());
            $entity->getModel()->scope()->clear();
        });

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Delete failed, exactly 1 row was expected to be affected');
        try {
            $entity3->delete();
        } finally {
            self::assertSameExportUnordered([
                ['id' => 1, 'name' => 'James'],
                ['id' => 2, 'name' => 'Roman'],
                ['id' => 3, 'name' => 'Jennifer'],
            ], $m->export());
        }
    }
}
