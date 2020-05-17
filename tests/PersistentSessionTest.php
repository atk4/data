<?php

namespace atk4\data\tests;

use atk4\core\AtkPhpunit;
use atk4\data\Model;
use atk4\data\Persistence;

/**
 * @coversDefaultClass \atk4\data\Persistence\Session
 */
class PersistentSessionTest extends AtkPhpunit\TestCase
{
    /**
     * Tests.
     */
    public function testSession()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John'],
                2 => ['name' => 'Sarah'],
            ],
        ];

        $p = new Persistence\Session($a);
        $this->assertSame($a, $p->data);

        $m = new Model($p, 'user');
        $m->addField('name');
        $this->assertSame($a, $p->data);
        $this->assertSame($a[$m->table], $m->export());

        $m->load(1);
        $this->assertSame('John', $m['name']);

        $m->load(2);
        $m['name'] = 'Jane';
        $m->save();

        $m->load(2);
        $this->assertSame('Jane', $m['name']);
        $this->assertSame($a, $p->data);
        $this->assertSame($a[$m->table], $m->export());

        // now clear data
        $p->clearData();
        $this->assertTrue(empty($p->data));
        $this->assertTrue(empty($a));
    }
}
