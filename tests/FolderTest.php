<?php

declare(strict_types=1);

namespace atk4\data\tests;

use atk4\data\Persistence;

class Folder extends \atk4\data\Model
{
    public $table = 'folder';

    protected function init(): void
    {
        parent::init();
        $this->addField('name');

        $this->hasMany('SubFolder', [new self(), 'their_field' => 'parent_id'])
            ->addField('count', ['aggregate' => 'count', 'field' => $this->expr('*')]);

        $this->hasOne('parent_id', new self())
            ->addTitle();

        $this->addField('is_deleted', ['type' => 'boolean']);
        $this->addCondition('is_deleted', false);
    }
}

/**
 * @coversDefaultClass \atk4\data\Model
 */
class FolderTest extends \atk4\schema\PhpunitTestCase
{
    public function testRate()
    {
        $this->setDb([
            'folder' => [
                ['parent_id' => 1, 'is_deleted' => 0, 'name' => 'Desktop'],
                ['parent_id' => 1, 'is_deleted' => 0, 'name' => 'My Documents'],
                ['parent_id' => 1, 'is_deleted' => 0, 'name' => 'My Videos'],
                ['parent_id' => 1, 'is_deleted' => 0, 'name' => 'My Projects'],
                ['parent_id' => 4, 'is_deleted' => 0, 'name' => 'Agile Data'],
                ['parent_id' => 4, 'is_deleted' => 0, 'name' => 'DSQL'],
                ['parent_id' => 4, 'is_deleted' => 0, 'name' => 'Agile Toolkit'],
                ['parent_id' => 4, 'is_deleted' => 1, 'name' => 'test-project'],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $f = new Folder($db);
        $f->load(4);

        $this->assertEquals([
            'id' => 4,
            'name' => 'My Projects',
            'count' => 3,
            'parent_id' => 1,
            'parent' => 'Desktop',
            'is_deleted' => 0,
        ], $f->get());
    }
}
