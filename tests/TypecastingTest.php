<?php

declare(strict_types=1);

namespace atk4\data\tests;

use atk4\data\Exception;
use atk4\data\Model;
use atk4\data\Persistence;

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
        return $this->format('H:i:s.u');
    }
}

class MyDateTime extends \DateTime
{
    public function __toString()
    {
        return $this->format('Y-m-d H:i:s.u');
    }
}

/**
 * @coversDefaultClass \atk4\data\Model
 */
class TypecastingTest extends \atk4\schema\PhpunitTestCase
{
    public function testType()
    {
        $a = [
            'types' => [
                [
                    'string' => 'foo',
                    'date' => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12.000000',
                    'time' => '12:00:50.000000',
                    'boolean' => 1,
                    'integer' => '2940',
                    'money' => '8.20',
                    'float' => '8.202343',
                    'array' => '[1,2,3]',
                ],
            ],
        ];
        $this->setDB($a);

        date_default_timezone_set('Asia/Seoul');

        $db = new Persistence\SQL($this->db->connection);

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

        $this->assertSame('foo', $m->get('string'));
        $this->assertTrue($m->get('boolean'));
        $this->assertSame(8.20, $m->get('money'));
        $this->assertEquals(new \DateTime('2013-02-20'), $m->get('date'));
        $this->assertEquals(new \DateTime('2013-02-20 20:00:12 UTC'), $m->get('datetime'));
        $this->assertEquals(new \DateTime('1970-01-01 12:00:50'), $m->get('time'));
        $this->assertSame(2940, $m->get('integer'));
        $this->assertSame([1, 2, 3], $m->get('array'));
        $this->assertSame(8.202343, $m->get('float'));

        $m->duplicate()->save();

        $a = [
            'types' => [
                1 => [
                    'id' => '1',
                    'string' => 'foo',
                    'date' => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12.000000',
                    'time' => '12:00:50.000000',
                    'boolean' => 1,
                    'integer' => 2940,
                    'money' => 8.20,
                    'float' => 8.202343,
                    'array' => '[1,2,3]',
                ],
                2 => [
                    'id' => '2',
                    'string' => 'foo',
                    'date' => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12.000000',
                    'time' => '12:00:50.000000',
                    'boolean' => '1',
                    'integer' => '2940',
                    'money' => '8.2',
                    'float' => '8.202343',
                    'array' => '[1,2,3]',
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
        if ($this->driverType === 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $a = [
            'types' => [
                1 => $v = [
                    'id' => 1,
                    'string' => '',
                    'notype' => '',
                    'date' => '',
                    'datetime' => '',
                    'time' => '',
                    'boolean' => '',
                    'integer' => '',
                    'money' => '',
                    'float' => '',
                    'array' => '',
                    'object' => '',
                ],
            ],
        ];
        $this->setDB($a);

        date_default_timezone_set('Asia/Seoul');

        $db = new Persistence\SQL($this->db->connection);

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
        $this->assertSame('', $m->get('string'));
        $this->assertSame('', $m->get('notype'));
        $this->assertNull($m->get('date'));
        $this->assertNull($m->get('datetime'));
        $this->assertNull($m->get('time'));
        $this->assertNull($m->get('boolean'));
        $this->assertNull($m->get('integer'));
        $this->assertNull($m->get('money'));
        $this->assertNull($m->get('float'));
        $this->assertNull($m->get('array'));
        $this->assertNull($m->get('object'));

        unset($v['id']);
        $m->set($v);

        $this->assertSame('', $m->get('string'));
        $this->assertSame('', $m->get('notype'));
        $this->assertNull($m->get('date'));
        $this->assertNull($m->get('datetime'));
        $this->assertNull($m->get('time'));
        $this->assertNull($m->get('boolean'));
        $this->assertNull($m->get('integer'));
        $this->assertNull($m->get('money'));
        $this->assertNull($m->get('float'));
        $this->assertNull($m->get('array'));
        $this->assertNull($m->get('object'));
        $this->assertSame([], $m->dirty);

        $m->save();
        $this->assertEquals($a, $this->getDB());

        $m->duplicate()->save();

        $a['types'][2] = [
            'id' => 2,
            'string' => '',
            'notype' => '',
            'date' => null,
            'datetime' => null,
            'time' => null,
            'boolean' => null,
            'integer' => null,
            'money' => null,
            'float' => null,
            'array' => null,
            'object' => null,
        ];

        $this->assertEquals($a, $this->getDB());
    }

    public function testTypecastNull()
    {
        if ($this->driverType === 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $a = [
            'test' => [
                1 => $v = ['id' => '1', 'a' => 1, 'b' => '', 'c' => null],
            ],
        ];
        $this->setDB($a);
        $db = new Persistence\SQL($this->db->connection);

        $m = new Model($db, ['table' => 'test']);
        $m->addField('a');
        $m->addField('b');
        $m->addField('c');

        unset($v['id']);
        $m->set($v);
        $m->save();

        $a['test'][2] = array_merge(['id' => '2'], $v);

        $this->assertEquals($a, $this->getDB());
    }

    public function testTypeCustom1()
    {
        $a = [
            'types' => [
                [
                    'date' => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12.235689',
                    'time' => '12:00:50.235689',
                    'b1' => 'Y',
                    'b2' => 'N',
                    'integer' => '2940',
                    'money' => '8.20',
                    'float' => '8.202343',
                    'rot13' => 'uryyb jbeyq',
                ],
            ],
        ];
        $this->setDB($a);
        $db = new Persistence\SQL($this->db->connection);

        date_default_timezone_set('Asia/Seoul');

        $m = new Model($db, ['table' => 'types']);

        $m->addField('date', ['type' => 'date', 'dateTimeClass' => MyDate::class]);
        $m->addField('datetime', ['type' => 'datetime', 'dateTimeClass' => MyDateTime::class]);
        $m->addField('time', ['type' => 'time', 'dateTimeClass' => MyTime::class]);
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

        $this->assertSame('hello world', $m->get('rot13'));
        $this->assertSame(1, (int) $m->id);
        $this->assertSame(1, (int) $m->get('id'));
        $this->assertSame('2013-02-21 05:00:12.235689', (string) $m->get('datetime'));
        $this->assertSame('2013-02-20', (string) $m->get('date'));
        $this->assertSame('12:00:50.235689', (string) $m->get('time'));

        $this->assertTrue($m->get('b1'));
        $this->assertFalse($m->get('b2'));

        $m->duplicate()->save()->delete(1);

        $a = [
            'types' => [
                2 => [
                    'id' => '2',
                    'date' => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12.235689',
                    'time' => '12:00:50.235689',
                    'b1' => 'Y',
                    'b2' => 'N',
                    'integer' => '2940',
                    'money' => '8.2', // here it will loose last zero and that's as expected
                    'float' => '8.202343',
                    'rot13' => 'uryyb jbeyq', // str_rot13(hello world)
                ],
            ],
        ];
        $this->assertEquals($a, $this->getDB());
    }

    public function testTryLoad()
    {
        $a = [
            'types' => [
                [
                    'date' => '2013-02-20',
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence\SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);

        $m->addField('date', ['type' => 'date', 'dateTimeClass' => MyDate::class]);

        $m->tryLoad(1);

        $this->assertTrue($m->get('date') instanceof MyDate);
    }

    public function testTryLoadAny()
    {
        $a = [
            'types' => [
                [
                    'date' => '2013-02-20',
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence\SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);

        $m->addField('date', ['type' => 'date', 'dateTimeClass' => MyDate::class]);

        $m->tryLoadAny();

        $this->assertTrue($m->get('date') instanceof MyDate);
    }

    public function testTryLoadBy()
    {
        $a = [
            'types' => [
                [
                    'date' => '2013-02-20',
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence\SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);

        $m->addField('date', ['type' => 'date', 'dateTimeClass' => MyDate::class]);

        $m->loadBy('id', 1);

        $this->assertTrue($m->get('date') instanceof MyDate);
    }

    public function testLoadBy()
    {
        $a = [
            'types' => [
                [
                    'date' => '2013-02-20',
                ],
            ],
        ];
        $this->setDB($a);
        $db = new Persistence\SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('date', ['type' => 'date', 'dateTimeClass' => MyDate::class]);

        $m->loadAny();
        $this->assertTrue($m->loaded());
        $d = $m->get('date');
        $m->unload();

        $m->loadBy('date', $d);
        $this->assertTrue($m->loaded());
        $m->unload();

        $m->addCondition('date', $d)->loadAny();
        $this->assertTrue($m->loaded());
    }

    public function testTypecastBoolean()
    {
        $db = new Persistence\SQL($this->db->connection);
        $m = new Model($db, 'job');

        $f = $m->addField('closed', ['type' => 'boolean', 'enum' => ['N', 'Y']]);

        $this->assertSame('N', $db->typecastSaveField($f, 'N'));
    }

    public function testTypecastTimezone()
    {
        $db = new Persistence\SQL($this->db->connection);
        $m = new Model($db, 'event');
        $dt = $m->addField('dt', ['type' => 'datetime', 'persist_timezone' => 'EEST']);
        $d = $m->addField('d', ['type' => 'date', 'persist_timezone' => 'EEST']);
        $t = $m->addField('t', ['type' => 'time', 'persist_timezone' => 'EEST']);

        date_default_timezone_set('UTC');
        $s = new \DateTime('Monday, 15-Aug-05 22:52:01 UTC');
        $this->assertSame('2005-08-16 00:52:01.000000', $db->typecastSaveField($dt, $s));
        $this->assertSame('2005-08-15', $db->typecastSaveField($d, $s));
        $this->assertSame('22:52:01.000000', $db->typecastSaveField($t, $s));
        $this->assertEquals(new \DateTime('Monday, 15-Aug-05 22:52:01 UTC'), $db->typecastLoadField($dt, '2005-08-16 00:52:01'));
        $this->assertEquals(new \DateTime('Monday, 15-Aug-05'), $db->typecastLoadField($d, '2005-08-15'));
        $this->assertEquals(new \DateTime('1970-01-01 22:52:01'), $db->typecastLoadField($t, '22:52:01'));

        date_default_timezone_set('Asia/Tokyo');

        $s = new \DateTime('Monday, 15-Aug-05 22:52:01 UTC');
        $this->assertSame('2005-08-16 00:52:01.000000', $db->typecastSaveField($dt, $s));
        $this->assertSame('2005-08-15', $db->typecastSaveField($d, $s));
        $this->assertSame('22:52:01.000000', $db->typecastSaveField($t, $s));
        $this->assertEquals(new \DateTime('Monday, 15-Aug-05 22:52:01 UTC'), $db->typecastLoadField($dt, '2005-08-16 00:52:01'));
        $this->assertEquals(new \DateTime('Monday, 15-Aug-05'), $db->typecastLoadField($d, '2005-08-15'));
        $this->assertEquals(new \DateTime('1970-01-01 22:52:01'), $db->typecastLoadField($t, '22:52:01'));

        date_default_timezone_set('America/Los_Angeles');

        $s = new \DateTime('Monday, 15-Aug-05 22:52:01'); // uses servers default timezone
        $this->assertSame('2005-08-16 07:52:01.000000', $db->typecastSaveField($dt, $s));
        $this->assertSame('2005-08-15', $db->typecastSaveField($d, $s));
        $this->assertSame('22:52:01.000000', $db->typecastSaveField($t, $s));
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
                    'date' => $sql_time,
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence\SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'datetime']);
        $m->loadAny();

        // must respect 'actual'
        $this->assertNotNull($m->get('ts'));
    }

    public function testBadTimestamp()
    {
        $sql_time = '20blah16-10-25 11:44:08';

        $a = [
            'types' => [
                [
                    'date' => $sql_time,
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence\SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'datetime']);
        $this->expectException(Exception::class);
        $m->loadAny();
    }

    public function testDirtyTimestamp()
    {
        $sql_time = '2016-10-25 11:44:08';

        $a = [
            'types' => [
                [
                    'date' => $sql_time,
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence\SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'datetime']);
        $m->loadAny();
        $m->set('ts', clone $m->get('ts'));

        $this->assertFalse($m->isDirty('ts'));
    }

    public function testTimestampSave()
    {
        $a = [
            'types' => [
                [
                    'date' => '',
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence\SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'date']);
        $m->loadAny();
        $m->set('ts', new \DateTime('2012-02-30'));
        $m->save();

        // stores valid date.
        $this->assertEquals(['types' => [1 => ['id' => 1, 'date' => '2012-03-01']]], $this->getDB());
    }

    public function testIntegerSave()
    {
        $db = new Persistence\SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('i', ['type' => 'integer']);

        $m->data['i'] = 1;
        $this->assertSame([], $m->dirty);

        $m->set('i', '1');
        $this->assertSame([], $m->dirty);

        $m->set('i', '2');
        $this->assertSame(['i' => 1], $m->dirty);

        $m->set('i', '1');
        $this->assertSame([], $m->dirty);

        // same test without type integer
        $m = new Model($db, ['table' => 'types']);
        $m->addField('i');

        $m->data['i'] = 1;
        $this->assertSame([], $m->dirty);

        $m->set('i', '1');
        $this->assertSame(1, $m->dirty['i']);

        $m->set('i', '2');
        $this->assertSame(1, $m->dirty['i']);

        $m->set('i', '1');
        $this->assertSame(1, $m->dirty['i']);

        $m->set('i', 1);
        $this->assertSame([], $m->dirty);
    }

    public function testDirtyTime()
    {
        $sql_time = '11:44:08';
        $sql_time_new = '12:34:56';

        $a = [
            'types' => [
                [
                    'date' => $sql_time,
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence\SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'time']);
        $m->loadAny();

        $m->set('ts', $sql_time_new);
        $this->assertTrue($m->isDirty('ts'));

        $m->set('ts', $sql_time);
        $this->assertFalse($m->isDirty('ts'));

        $m->set('ts', $sql_time_new);
        $this->assertTrue($m->isDirty('ts'));
    }

    public function testDirtyTimeAfterSave()
    {
        $sql_time = '11:44:08';
        $sql_time_new = '12:34:56';

        $a = [
            'types' => [
                [
                    'date' => null,
                ],
            ], ];
        $this->setDB($a);
        $db = new Persistence\SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'time']);
        $m->loadAny();

        $m->set('ts', $sql_time);
        $this->assertTrue($m->isDirty('ts'));

        $m->save();
        $m->reload();

        $m->set('ts', $sql_time);
        $this->assertFalse($m->isDirty('ts'));

        $m->set('ts', $sql_time_new);
        $this->assertTrue($m->isDirty('ts'));
    }
}
