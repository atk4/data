<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

class Model_Test extends Model
{
    public $table = 'test';
    
    protected function init(): void
    {
        parent::init();
        $this->addField('static');
        $this->addField('changing');
    }
}

class ModelCheckpointTest extends TestCase
{
    public function testCheckpoint()
    {
        $this->setDb([
            'test' => [
                ['id' => 1, 'static' => 'unchanging', 'changing' => 'basevalue'],
            ],
        ]);
        
        $m = new Model_Test($this->db);
        
        $m = $m->load(1);
        $m->set('changing', 'newvalue');
        $m->checkpoint(1);
        $this->assertEquals([],$m->getDirtyVsCheckpoint(1));
        $this->assertEquals(['changing' => 'basevalue'], $m->getDirtyVsCheckpoint());
        $m->set('changing', 'basevalue');
        $this->assertEqauals([], $m->getDirtyVsCheckpoint());
        $this->assertEquals(['changing' => 'newvalue'], $m->getDirtyVsCheckpoint(1));
    }
}