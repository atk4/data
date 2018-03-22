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
class TypecastingTest extends \atk4\schema\PHPUnit_SchemaTestCase
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
        $m->addField('array', ['type' => 'array']);
        $m->load(1);

        $this->assertSame('foo', $m['string']);
        $this->assertSame(true, $m['boolean']);
        $this->assertSame(8.20, $m['money']);
        $this->assertEquals(new \DateTime('2013-02-20'), $m['date']);
        $this->assertEquals(new \DateTime('2013-02-20 20:00:12 UTC'), $m['datetime']);
        $this->assertEquals(new \DateTime('1970-01-01 12:00:50'), $m['time']);
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

    public function testEmptyValues()
    {
        if ($this->driver == 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $a = [
            'types' => [
                1 => $v = [
                    'id'       => 1,
                    'string'   => '',
                    'notype'   => '',
                    'date'     => '',
                    'datetime' => '',
                    'time'     => '',
                    'boolean'  => '',
                    'integer'  => '',
                    'money'    => '',
                    'float'    => '',
                    'array'    => '',
                    'object'   => '',
                ],
            ],
        ];
        $this->setDB($a);

        date_default_timezone_set('Asia/Seoul');

        $db = new Persistence_SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('string', ['type' => 'string']);
        $m->addField('notype');
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('integer', ['type' => 'integer']);
        $m->addField('money', ['type' => 'money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('array', ['type' => 'array']);
        $m->addField('object', ['type' => 'object']);
        $m->load(1);

        // Only
        $this->assertSame('', $m['string']);
        $this->assertSame('', $m['notype']);
        $this->assertSame(null, $m['date']);
        $this->assertSame(null, $m['datetime']);
        $this->assertSame(null, $m['time']);
        $this->assertSame(null, $m['boolean']);
        $this->assertSame(null, $m['integer']);
        $this->assertSame(null, $m['money']);
        $this->assertSame(null, $m['float']);
        $this->assertSame(null, $m['array']);
        $this->assertSame(null, $m['object']);

        unset($v['id']);
        $m->set($v);

        $this->assertSame('', $m['string']);
        $this->assertSame('', $m['notype']);
        $this->assertSame(null, $m['date']);
        $this->assertSame(null, $m['datetime']);
        $this->assertSame(null, $m['time']);
        $this->assertSame(null, $m['boolean']);
        $this->assertSame(null, $m['integer']);
        $this->assertSame(null, $m['money']);
        $this->assertSame(null, $m['float']);
        $this->assertSame(null, $m['array']);
        $this->assertSame(null, $m['object']);
        $this->assertEquals([], $m->dirty);

        $m->save();
        $this->assertEquals($a, $this->getDB());

        $m->duplicate()->save();

        $a['types'][2] = [
                    'id'       => 2,
                    'string'   => '',
                    'notype'   => '',
                    'date'     => null,
                    'datetime' => null,
                    'time'     => null,
                    'boolean'  => null,
                    'integer'  => null,
                    'money'    => null,
                    'float'    => null,
                    'array'    => null,
                    'object'   => null,
        ];

        $this->assertEquals($a, $this->getDB());
    }

    public function testTypecastNull()
    {
        if ($this->driver == 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $a = [
            'test' => [
                1 => $v = ['id' => '1', 'a' => 1, 'b' => '', 'c' => null],
            ],
        ];
        $this->setDB($a);
        $db = new Persistence_SQL($this->db->connection);

        $m = new Model($db, ['table' => 'test']);
        $m->addField('a');
        $m->addField('b');
        $m->addField('c');

        unset($v['id']);
        $m->set($v);
        $m->save();

        $a['test'][2] = array_merge(['id'=>'2'], $v);

        $this->assertEquals($a, $this->getDB());
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

        $m->addField('rot13', ['typecast' => [$rot, $rot]]);

        $m->load(1);

        $this->assertSame('hello world', $m['rot13']);
        $this->assertSame(1, (int) $m->id);
        $this->assertSame(1, (int) $m['id']);
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

    public function testTypecastBoolean()
    {
        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'job');

        $f = $m->addField('closed', ['type' => 'boolean', 'enum' => ['N', 'Y']]);

        $this->assertEquals('N', $db->typecastSaveField($f, 'N'));
    }

    public function testTypecastTimezone()
    {
        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'event');
        $dt = $m->addField('dt', ['type' => 'datetime', 'persist_timezone' => 'EEST']);
        $d = $m->addField('d', ['type' => 'date', 'persist_timezone' => 'EEST']);
        $t = $m->addField('t', ['type' => 'time', 'persist_timezone' => 'EEST']);

        date_default_timezone_set('UTC');
        $s = new \DateTime('Monday, 15-Aug-05 22:52:01 UTC');
        $this->assertEquals('2005-08-16 00:52:01', $db->typecastSaveField($dt, $s));
        $this->assertEquals('2005-08-15', $db->typecastSaveField($d, $s));
        $this->assertEquals('22:52:01', $db->typecastSaveField($t, $s));
        $this->assertEquals(new \DateTime('Monday, 15-Aug-05 22:52:01 UTC'), $db->typecastLoadField($dt, '2005-08-16 00:52:01'));
        $this->assertEquals(new \DateTime('Monday, 15-Aug-05'), $db->typecastLoadField($d, '2005-08-15'));
        $this->assertEquals(new \DateTime('1970-01-01 22:52:01'), $db->typecastLoadField($t, '22:52:01'));

        date_default_timezone_set('Asia/Tokyo');

        $s = new \DateTime('Monday, 15-Aug-05 22:52:01 UTC');
        $this->assertEquals('2005-08-16 00:52:01', $db->typecastSaveField($dt, $s));
        $this->assertEquals('2005-08-15', $db->typecastSaveField($d, $s));
        $this->assertEquals('22:52:01', $db->typecastSaveField($t, $s));
        $this->assertEquals(new \DateTime('Monday, 15-Aug-05 22:52:01 UTC'), $db->typecastLoadField($dt, '2005-08-16 00:52:01'));
        $this->assertEquals(new \DateTime('Monday, 15-Aug-05'), $db->typecastLoadField($d, '2005-08-15'));
        $this->assertEquals(new \DateTime('1970-01-01 22:52:01'), $db->typecastLoadField($t, '22:52:01'));

        date_default_timezone_set('America/Los_Angeles');

        $s = new \DateTime('Monday, 15-Aug-05 22:52:01'); // uses servers default timezone
        $this->assertEquals('2005-08-16 07:52:01', $db->typecastSaveField($dt, $s));
        $this->assertEquals('2005-08-15', $db->typecastSaveField($d, $s));
        $this->assertEquals('22:52:01', $db->typecastSaveField($t, $s));
        $this->assertEquals(new \DateTime('Monday, 15-Aug-05 22:52:01 America/Los_Angeles'), $db->typecastLoadField($dt, '2005-08-16 07:52:01'));
        $this->assertEquals(new \DateTime('Monday, 15-Aug-05'), $db->typecastLoadField($d, '2005-08-15'));
        $this->assertEquals(new \DateTime('1970-01-01 22:52:01'), $db->typecastLoadField($t, '22:52:01'));
    }

    public function testTimestamp()
    {
        $sql_time = '2016-10-25 11:44:08';

        $a = [
            'types' => [
                [
                    'date'     => $sql_time,
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence_SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'datetime']);
        $m->loadAny();

        // must respect 'actual'
        $this->assertNotNull($m['ts']);
    }

    /**
     * @expectedException Exception
     */
    public function testBadTimestamp()
    {
        $sql_time = '20blah16-10-25 11:44:08';

        $a = [
            'types' => [
                [
                    'date'     => $sql_time,
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence_SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'datetime']);
        $m->loadAny();
    }

    public function testDirtyTimestamp()
    {
        $sql_time = '2016-10-25 11:44:08';

        $a = [
            'types' => [
                [
                    'date'     => $sql_time,
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence_SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'datetime']);
        $m->loadAny();
        $m['ts'] = clone $m['ts'];

        $this->assertFalse($m->isDirty('ts'));
    }

    public function testTimestampSave()
    {
        $a = [
            'types' => [
                [
                    'date'     => '',
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence_SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'date']);
        $m->loadAny();
        $m['ts'] = new \DateTime('2012-02-30');
        $m->save();

        // stores valid date.
        $this->assertEquals(['types' => [1 => ['id' => 1, 'date' => '2012-03-01']]], $this->getDB());
    }

    public function testIntegerSave()
    {
        $db = new Persistence_SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('i', ['type' => 'integer']);

        $m->data['i'] = 1;
        $this->assertSame([], $m->dirty);

        $m['i'] = '1';
        $this->assertSame([], $m->dirty);

        $m['i'] = '2';
        $this->assertSame(['i' => 1], $m->dirty);

        $m['i'] = '1';
        $this->assertSame([], $m->dirty);

        // same test without type integer
        $m = new Model($db, ['table' => 'types']);
        $m->addField('i');

        $m->data['i'] = 1;
        $this->assertSame([], $m->dirty);

        $m['i'] = '1';
        $this->assertSame(1, $m->dirty['i']);

        $m['i'] = '2';
        $this->assertSame(1, $m->dirty['i']);

        $m['i'] = '1';
        $this->assertSame(1, $m->dirty['i']);

        $m['i'] = 1;
        $this->assertSame([], $m->dirty);
    }
}
