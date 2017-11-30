<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;
use atk4\data\Persistence_Array;

class PasswordTest extends TestCase
{
    public function testPasswordField()
    {
        $m = new Model();//$db, 'job');

        $m->addField('p', ['Password']);

        $m['p'] = 'mypass';

        // when setting password, you cannot retrieve it back
        $this->assertEquals('mypass', $m['p']);

        // password changed, so it's dirty.
        $this->assertEquals(true, $m->isDirty('p'));

        $this->assertEquals(false, $m->compare('p', 'badpass'));
        $this->assertEquals(true, $m->compare('p', 'mypass'));
    }

    public function testPasswordPersistence1()
    {
        $a = [];
        $p = new Persistence_Array($a);
        $m = new Model($p);

        $m->addField('p', ['Password']);

        $m['p'] = 'mypass';

        $this->assertEquals('mypass', $m['p']);

        $m->save();

        $enc = $a['data'][1]['p'];

        $this->assertTrue(is_string($enc));
        $this->assertNotEquals('mypass', $enc);

        // should have reloaded also

        $this->assertNull($m['p']);

        $this->assertFalse($m->compare('p', 'badpass'));
        $this->assertTrue($m->compare('p', 'mypass'));

        // password shouldnt be dirty here
        $this->assertFalse($m->isDirty('p'));

        $m['p'] = 'newpass';

        $this->assertTrue($m->isDirty('p'));
        $this->assertFalse($m->compare('p', 'mypass'));
        $this->assertTrue($m->compare('p', 'newpass'));

        $m->save();

        // will have new hash
        $this->assertNotEquals($enc, $a['data'][1]['p']);
    }
}
