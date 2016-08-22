<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class TypecastingTest extends SQLTestCase
{
    public function testType()
    {
        $a = [
            'types' => [
                [
                    'date'     => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12',
                    'time'     => '12:00:50',
                    'boolean'  => '1',
                    'int'      => '2940',
                    'money'    => '8.20',
                    'float'    => '8.202343',
                ],
            ], ];
        $this->setDB($a);

        date_default_timezone_set('Asia/Seoul');

        $db = new Persistence_SQL($this->db->connection);

        $m = new Model($db, ['table' => 'types']);
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('money', ['type' => 'money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('int', ['type' => 'int']);
        $m->load(1);

        date_default_timezone_set('UTC');


        $this->assertSame(true, $m['boolean']);
        $this->assertSame(8.20, $m['money']);
        $this->assertEquals(new \DateTime('2013-02-20'), $m['date']);
        $this->assertEquals(new \DateTime('2013-02-20 20:00:12'), $m['datetime']);
        $this->assertEquals(new \DateTime('12:00:50'), $m['time']);
        $this->assertSame(2940, $m['int']);
        $this->assertSame(8.202343, $m['float']);


        $m->duplicate()->save();

        $a = [
            'types' => [
                1 => [
                    'id'       => '1',
                    'date'     => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12',
                    'time'     => '12:00:50',
                    'boolean'  => 1,
                    'int'      => 2940,
                    'money'    => 8.20,
                    'float'    => 8.202343,
                ],
                2 => [
                    'id'       => '2',
                    'date'     => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12',
                    'time'     => '12:00:50',
                    'boolean'  => '1',
                    'int'      => '2940',
                    'money'    => '8.2',
                    'float'    => '8.202343',
                ],
            ], ];
        $this->assertEquals($a, $this->getDB());

        list($first, $duplicate) = $m->export();

        unset($first['id']);
        unset($duplicate['id']);

        $this->assertEquals($first, $duplicate);
    }
}
