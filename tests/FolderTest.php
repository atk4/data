<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

class Folder extends Model
{
    public $table = 'folder';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');

        $this->hasMany('SubFolder', ['model' => [self::class], 'theirField' => 'parent_id'])
            ->addField('count', ['aggregate' => 'count', 'field' => $this->getPersistence()->expr($this, '*')]);

        $this->hasOne('parent_id', ['model' => [self::class]])
            ->addTitle();

        $this->addField('is_deleted', ['type' => 'boolean']);
        $this->addCondition('is_deleted', false);
    }
}

class FolderTest extends TestCase
{
    public function testRate(): void
    {
        $this->setDb([
            'folder' => [
                ['parent_id' => 1, 'is_deleted' => false, 'name' => 'Desktop'],
                ['parent_id' => 1, 'is_deleted' => false, 'name' => 'My Documents'],
                ['parent_id' => 1, 'is_deleted' => false, 'name' => 'My Videos'],
                ['parent_id' => 1, 'is_deleted' => false, 'name' => 'My Projects'],
                ['parent_id' => 4, 'is_deleted' => false, 'name' => 'Agile Data'],
                ['parent_id' => 4, 'is_deleted' => false, 'name' => 'DSQL'],
                ['parent_id' => 4, 'is_deleted' => false, 'name' => 'Agile Toolkit'],
                ['parent_id' => 4, 'is_deleted' => true, 'name' => 'test-project'],
            ],
        ]);

        $f = new Folder($this->db);
        $this->createMigrator()->createForeignKey($f->getReference('SubFolder'));
        $f = $f->load(4);

        self::assertSame([
            'id' => 4,
            'name' => 'My Projects',
            'count' => '3',
            'parent_id' => 1,
            'parent' => 'Desktop',
            'is_deleted' => false,
        ], $f->get());
    }
}
