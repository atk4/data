<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

class MyDate extends \DateTime
{
    public function __toString()
    {
        return $this->format('Y-m-d');
    }
}

class MyTime extends \DateTime
{
    public function __toString()
    {
        return $this->format('H:i:s');
    }
}

class MyDateTime extends \DateTime
{
    public function __toString()
    {
        return date('Y-m-d H:i:s', $this->format('U'));
    }
}

/**
 * @coversDefaultClass \atk4\data\Model
 */
class TypecastingTest extends SQLTestCase
{
    //    public $debug = true;
    public function testType()
    {
        $a = [
            'types' => [
                [
                    'string'   => 'foo',
                    'date'     => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12',
                    'time'     => '12:00:50',
                    'boolean'  => '1',
                    'integer'  => '2940',
                    'money'    => '8.20',
                    'float'    => '8.202343',
                    'array'    => '[1,2,3]',
                ],
            ],
        ];
        $this->setDB($a);

        date_default_timezone_set('Asia/Seoul');

        $db = new Persistence_SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('string', ['type' => 'string']);
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('money', ['type' => 'money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('integer', ['type' => 'integer']);
        $m->addField('array', ['type' => 'struct']);
        $m->load(1);

        date_default_timezone_set('UTC');

        $this->assertSame('foo', $m['string']);
        $this->assertSame(true, $m['boolean']);
        $this->assertSame(8.20, $m['money']);
        $this->assertEquals(new \DateTime('2013-02-20'), $m['date']);
        $this->assertEquals(new \DateTime('2013-02-20 20:00:12'), $m['datetime']);
        $this->assertEquals(new \DateTime('12:00:50'), $m['time']);
        $this->assertSame(2940, $m['integer']);
        $this->assertEquals([1, 2, 3], $m['array']);
        $this->assertSame(8.202343, $m['float']);


        $m->duplicate()->save();

        $a = [
            'types' => [
                1 => [
                    'id'       => '1',
                    'string'   => 'foo',
                    'date'     => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12',
                    'time'     => '12:00:50',
                    'boolean'  => 1,
                    'integer'  => 2940,
                    'money'    => 8.20,
                    'float'    => 8.202343,
                    'array'    => '[1,2,3]',
                ],
                2 => [
                    'id'       => '2',
                    'string'   => 'foo',
                    'date'     => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12',
                    'time'     => '12:00:50',
                    'boolean'  => '1',
                    'integer'  => '2940',
                    'money'    => '8.2',
                    'float'    => '8.202343',
                    'array'    => '[1,2,3]',
                ],
            ], ];
        $this->assertEquals($a, $this->getDB());

        list($first, $duplicate) = $m->export();

        unset($first['id']);
        unset($duplicate['id']);

        $this->assertEquals($first, $duplicate);
    }

    public function testTypeCustom1()
    {
        $a = [
            'types' => [
                [
                    'date'     => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12',
                    'time'     => '12:00:50',
                    'b1'       => 'Y',
                    'b2'       => 'N',
                    'integer'  => '2940',
                    'money'    => '8.20',
                    'float'    => '8.202343',
                    'rot13'    => 'uryyb jbeyq',
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence_SQL($this->db->connection);

        date_default_timezone_set('Asia/Seoul');

        $m = new Model($db, ['table' => 'types']);

        $m->addField('date', ['type' => 'date', 'dateTimeClass' => '\atk4\data\tests\MyDate']);
        $m->addField('datetime', ['type' => 'datetime', 'dateTimeClass' => '\atk4\data\tests\MyDateTime']);
        $m->addField('time', ['type' => 'time', 'dateTimeClass' => '\atk4\data\tests\MyTime']);
        $m->addField('b1', ['type' => 'boolean', 'enum' => ['N', 'Y']]);
        $m->addField('b2', ['type' => 'boolean', 'enum' => ['N', 'Y']]);
        $m->addField('money', ['type' => 'money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('integer', ['type' => 'integer']);

        $rot = function ($v) {
            return str_rot13($v);
        };

        $m->addField('rot13', ['loadCallback' => $rot, 'saveCallback' => $rot]);

        $m->load(1);

        $this->assertSame('hello world', $m['rot13']);
        $this->assertSame(1, $m->id);
        $this->assertSame(1, $m['id']);
        $this->assertEquals('2013-02-21 05:00:12', (string) $m['datetime']);
        $this->assertEquals('2013-02-20', (string) $m['date']);
        $this->assertEquals('12:00:50', (string) $m['time']);

        $this->assertEquals(true, $m['b1']);
        $this->assertEquals(false, $m['b2']);

        $m->duplicate()->save()->delete(1);

        $a = [
            'types' => [
                2 => [
                    'id'       => '2',
                    'date'     => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12',
                    'time'     => '12:00:50',
                    'b1'       => 'Y',
                    'b2'       => 'N',
                    'integer'  => '2940',
                    'money'    => '8.20',
                    'float'    => '8.202343',
                    'rot13'    => 'uryyb jbeyq', // str_rot13(hello world)
                ],
            ], ];
        $this->assertEquals($a, $this->getDB());
    }

    public function testTryLoad()
    {
        $a = [
            'types' => [
                [
                    'date'     => '2013-02-20',
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence_SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);

        $m->addField('date', ['type' => 'date', 'dateTimeClass' => '\atk4\data\tests\MyDate']);

        $m->tryLoad(1);

        $this->assertTrue($m['date'] instanceof MyDate);
    }

    public function testTryLoadAny()
    {
        $a = [
            'types' => [
                [
                    'date'     => '2013-02-20',
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence_SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);

        $m->addField('date', ['type' => 'date', 'dateTimeClass' => '\atk4\data\tests\MyDate']);

        $m->tryLoadAny();

        $this->assertTrue($m['date'] instanceof MyDate);
    }

    public function testTryLoadBy()
    {
        $a = [
            'types' => [
                [
                    'date'     => '2013-02-20',
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence_SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);

        $m->addField('date', ['type' => 'date', 'dateTimeClass' => '\atk4\data\tests\MyDate']);

        $m->loadBy('id', 1);

        $this->assertTrue($m['date'] instanceof MyDate);
    }

    public function testLoadBy()
    {
        $a = [
            'types' => [
                [
                    'date'     => '2013-02-20',
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence_SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('date', ['type' => 'date', 'dateTimeClass' => '\atk4\data\tests\MyDate']);
        $m->loadAny();
        $d = $m['date'];
        $m->unload();

        $m->loadBy('date', $d)->unload();

        $m->addCondition('date', $d)->loadAny();
    }
}
