<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

class PersistentArrayOfStringsTest extends TestCase
{
    /**
     * Test typecasting.
     */
    public function testTypecasting(): void
    {
        $p = new Persistence\ArrayOfStrings([
            'user' => [],
        ]);
        $m = new Model($p, ['table' => 'user']);
        $m->addField('string', ['type' => 'string']);
        $m->addField('text', ['type' => 'text']);
        $m->addField('integer', ['type' => 'integer']);
        $m->addField('money', ['type' => 'money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('boolean_enum', ['type' => 'boolean', 'enum' => ['N', 'Y']]);
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('json_arr', ['type' => 'json']);
        $m->addField('json_obj', ['type' => 'json']);
        $m->addField('object', ['type' => 'object']);

        $mm = $m->createEntity();
        $mm->setMulti([
            'string' => "Two\r\nLines  ",
            'text' => "Two\r\nLines  ",
            'integer' => 123,
            'money' => 123.45,
            'float' => 123.456789,
            'boolean' => true,
            'boolean_enum' => 'N',
            'date' => new \DateTime('2019-01-20T12:23:34+00:00'),
            'datetime' => new \DateTime('2019-01-20T12:23:34+00:00'),
            'time' => new \DateTime('2019-01-20T12:23:34+00:00'),
            'json_arr' => ['bar', 123, ['a', 'b']],
            'json_obj' => ['foo' => 'bar', 'int' => 123, 'rows' => ['a', 'b']],
            'object' => (object) ['foo' => 'bar', 'int' => 123, 'rows' => ['a', 'b']],
        ]);
        $mm->saveAndUnload();

        // no typecasting option set in export()
        $data = $m->export(null, null, false);
        $this->assertSame([1 => [
            'id' => 1,
            'string' => 'TwoLines',
            'text' => "Two\nLines",
            'integer' => 123,
            'money' => 123.45,
            'float' => 123.456789,
            'boolean' => true,
            'boolean_enum' => 'N',
            'date' => '2019-01-20',
            'datetime' => '2019-01-20 12:23:34.000000',
            'time' => '12:23:34.000000',
            'json_arr' => '["bar",123,["a","b"]]',
            'json_obj' => '{"foo":"bar","int":123,"rows":["a","b"]}',
            'object' => 'O:8:"stdClass":3:{s:3:"foo";s:3:"bar";s:3:"int";i:123;s:4:"rows";a:2:{i:0;s:1:"a";i:1;s:1:"b";}}',
        ]], $data);

        // typecasting enabled in export()
        $data = $m->export(null, null, true);
        $this->assertInstanceOf('DateTime', $data[1]['date']);
        $this->assertInstanceOf('DateTime', $data[1]['datetime']);
        $this->assertInstanceOf('DateTime', $data[1]['time']);
        $this->assertTrue(is_array($data[1]['json_arr']));
        $this->assertTrue(is_array($data[1]['json_obj']));
        $this->assertTrue(is_object($data[1]['object']));
    }
}
