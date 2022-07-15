<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;

class TypecastingTest extends TestCase
{
    /** @var string */
    private $defaultTzBackup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultTzBackup = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTzBackup);

        parent::tearDown();
    }

    public function testType(): void
    {
        $dbData = [
            'types' => [
                [
                    'string' => 'foo',
                    'date' => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12.000000',
                    'time' => '12:00:50.000000',
                    'boolean' => true,
                    'integer' => '2940',
                    'money' => '8.20',
                    'float' => 8.20234376757473,
                    'json' => '[1,2,3]',
                ],
            ],
        ];
        $this->setDb($dbData);

        date_default_timezone_set('Asia/Seoul');

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('string', ['type' => 'string']);
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('money', ['type' => 'atk4_money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('integer', ['type' => 'integer']);
        $m->addField('json', ['type' => 'json']);
        $mm = $m->load(1);

        $this->assertSame('foo', $mm->get('string'));
        $this->assertTrue($mm->get('boolean'));
        $this->assertSame(8.20, $mm->get('money'));
        $this->assertEquals(new \DateTime('2013-02-20 UTC'), $mm->get('date'));
        $this->assertEquals(new \DateTime('2013-02-20 20:00:12 UTC'), $mm->get('datetime'));
        $this->assertEquals(new \DateTime('1970-01-01 12:00:50 UTC'), $mm->get('time'));
        $this->assertSame(2940, $mm->get('integer'));
        $this->assertSame([1, 2, 3], $mm->get('json'));
        $this->assertSame(8.20234376757473, $mm->get('float'));

        $m->createEntity()->setMulti(array_diff_key($mm->get(), ['id' => true]))->save();

        $dbData = [
            'types' => [
                1 => [
                    'id' => '1',
                    'string' => 'foo',
                    'date' => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12.000000',
                    'time' => '12:00:50.000000',
                    'boolean' => 1,
                    'integer' => 2940,
                    'money' => 8.2,
                    'float' => 8.20234376757473,
                    'json' => '[1,2,3]',
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
                    'float' => 8.20234376757473,
                    'json' => '[1,2,3]',
                ],
            ],
        ];
        $this->assertEquals($dbData, $this->getDb());

        [$first, $duplicate] = $m->export();

        unset($first['id']);
        unset($duplicate['id']);

        $this->assertEquals($first, $duplicate);

        $m->load(2)->set('float', 8.20234376757474)->save();
        $this->assertSame(8.20234376757474, $m->load(2)->get('float'));
        $m->load(2)->set('float', 8.202343767574732)->save();
        // pdo_sqlite in truncating float, see https://github.com/php/php-src/issues/8510
        // fixed since PHP 8.1, but if converted in SQL to string explicitly, the result is still wrong
        if (!$this->getDatabasePlatform() instanceof SqlitePlatform || \PHP_VERSION_ID >= 80100) {
            $this->assertSame(8.202343767574732, $m->load(2)->get('float'));
        }
    }

    public function testEmptyValues(): void
    {
        // Oracle always converts empty string to null
        // see https://stackoverflow.com/questions/13278773/null-vs-empty-string-in-oracle#13278879
        $emptyStringValue = $this->getDatabasePlatform() instanceof OraclePlatform ? null : '';

        $dbData = [
            'types' => [
                1 => $row = [
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
                    'json' => '',
                    'object' => '',
                ],
            ],
        ];
        $this->setDb($dbData);

        date_default_timezone_set('Asia/Seoul');

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('string', ['type' => 'string']);
        $m->addField('notype');
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('integer', ['type' => 'integer']);
        $m->addField('money', ['type' => 'atk4_money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('json', ['type' => 'json']);
        $m->addField('object', ['type' => 'object']);
        $mm = $m->load(1);

        // Only
        $this->assertSame($emptyStringValue, $mm->get('string'));
        $this->assertSame($emptyStringValue, $mm->get('notype'));
        $this->assertNull($mm->get('date'));
        $this->assertNull($mm->get('datetime'));
        $this->assertNull($mm->get('time'));
        $this->assertNull($mm->get('boolean'));
        $this->assertNull($mm->get('integer'));
        $this->assertNull($mm->get('money'));
        $this->assertNull($mm->get('float'));
        $this->assertNull($mm->get('json'));
        $this->assertNull($mm->get('object'));

        unset($row['id']);
        $mm->setMulti($row);

        $this->assertSame('', $mm->get('string'));
        $this->assertSame('', $mm->get('notype'));
        $this->assertNull($mm->get('date'));
        $this->assertNull($mm->get('datetime'));
        $this->assertNull($mm->get('time'));
        $this->assertNull($mm->get('boolean'));
        $this->assertNull($mm->get('integer'));
        $this->assertNull($mm->get('money'));
        $this->assertNull($mm->get('float'));
        $this->assertNull($mm->get('json'));
        $this->assertNull($mm->get('object'));
        if (!$this->getDatabasePlatform() instanceof OraclePlatform) { // @TODO IMPORTANT we probably want to cast to string for Oracle on our own, so dirty array stay clean!
            $this->assertSame([], $mm->getDirtyRef());
        }

        $mm->save();
        $this->assertEquals($dbData, $this->getDb());

        $m->createEntity()->setMulti(array_diff_key($mm->get(), ['id' => true]))->save();

        $dbData['types'][2] = [
            'id' => 2,
            'string' => $emptyStringValue,
            'notype' => $emptyStringValue,
            'date' => null,
            'datetime' => null,
            'time' => null,
            'boolean' => null,
            'integer' => null,
            'money' => null,
            'float' => null,
            'json' => null,
            'object' => null,
        ];

        $this->assertEquals($dbData, $this->getDb());
    }

    public function testTypecastNull(): void
    {
        $dbData = [
            'test' => [
                1 => $row = ['id' => '1', 'a' => 1, 'b' => '', 'c' => null],
            ],
        ];
        $this->setDb($dbData);

        $m = new Model($this->db, ['table' => 'test']);
        $m->addField('a');
        $m->addField('b');
        $m->addField('c');
        $m = $m->createEntity();

        unset($row['id']);
        $m->setMulti($row);
        $m->save();

        $dbData['test'][2] = array_merge(['id' => '2'], $row);

        $this->assertEquals($dbData, $this->getDb());
    }

    public function testTypeCustom1(): void
    {
        $dbData = [
            'types' => [
                $row = [
                    'date' => '2013-02-20',
                    'datetime' => '2013-02-20 20:00:12.235689',
                    'time' => '12:00:50.235689',
                    'b1' => true,
                    'b2' => false,
                    'integer' => '2940',
                    'money' => '8.20',
                    'float' => 8.20234376757473,
                ],
            ],
        ];
        $this->setDb($dbData);

        date_default_timezone_set('Asia/Seoul');

        $m = new Model($this->db, ['table' => 'types']);

        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('b1', ['type' => 'boolean']);
        $m->addField('b2', ['type' => 'boolean']);
        $m->addField('money', ['type' => 'atk4_money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('integer', ['type' => 'integer']);

        $mm = $m->load(1);

        $this->assertSame(1, $mm->getId());
        $this->assertSame(1, $mm->get('id'));
        $this->assertSame('2013-02-21 05:00:12.235689', $mm->get('datetime')->format('Y-m-d H:i:s.u'));
        $this->assertSame('2013-02-20', $mm->get('date')->format('Y-m-d'));
        $this->assertSame('12:00:50.235689', $mm->get('time')->format('H:i:s.u'));

        $this->assertTrue($mm->get('b1'));
        $this->assertFalse($mm->get('b2'));

        $m->createEntity()->setMulti(array_diff_key($mm->get(), ['id' => true]))->save();
        $m->delete(1);

        unset($dbData['types'][0]);
        $row['b2'] = '0'; // fix false == '0', see https://github.com/sebastianbergmann/phpunit/issues/4967, remove once fixed
        $row['money'] = '8.2'; // here it will loose last zero and that's as expected
        $dbData['types'][2] = array_merge(['id' => '2'], $row);

        $this->assertEquals($dbData, $this->getDb());
    }

    public function testTryLoad(): void
    {
        $this->setDb([
            'types' => [
                [
                    'date' => '2013-02-20',
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);

        $m->addField('date', ['type' => 'date']);

        $m = $m->tryLoad(1);

        $this->assertInstanceOf(\DateTime::class, $m->get('date'));
    }

    public function testTryLoadAny(): void
    {
        $this->setDb([
            'types' => [
                [
                    'date' => '2013-02-20',
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);

        $m->addField('date', ['type' => 'date']);

        $m = $m->tryLoadAny();

        $this->assertInstanceOf(\DateTime::class, $m->get('date'));
    }

    public function testTryLoadBy(): void
    {
        $this->setDb([
            'types' => [
                [
                    'date' => '2013-02-20',
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);

        $m->addField('date', ['type' => 'date']);

        $m = $m->loadBy('id', 1);

        $this->assertInstanceOf(\DateTime::class, $m->get('date'));
    }

    public function testLoadBy(): void
    {
        $this->setDb([
            'types' => [
                [
                    'date' => '2013-02-20',
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('date', ['type' => 'date']);

        $m2 = $m->loadOne();
        $this->assertTrue($m2->isLoaded());
        $d = $m2->get('date');
        $m2->unload();

        $m2 = $m->loadBy('date', $d);
        $this->assertTrue($m2->isLoaded());
        $m2->unload();

        $m2 = $m->addCondition('date', $d)->loadOne();
        $this->assertTrue($m2->isLoaded());
    }

    public function testTimestamp(): void
    {
        $sqlTime = '2016-10-25 11:44:08';

        $this->setDb([
            'types' => [
                [
                    'date' => $sqlTime,
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'datetime']);
        $m = $m->loadOne();

        // must respect 'actual'
        $this->assertNotNull($m->get('ts'));
    }

    public function testBadTimestamp(): void
    {
        $sqlTime = '20blah16-10-25 11:44:08';

        $this->setDb([
            'types' => [
                [
                    'date' => $sqlTime,
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'datetime']);
        $this->expectException(Exception::class);
        $m = $m->loadOne();
    }

    public function testDirtyTimestamp(): void
    {
        $sqlTime = '2016-10-25 11:44:08';

        $this->setDb([
            'types' => [
                [
                    'date' => $sqlTime,
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'datetime']);
        $m = $m->loadOne();
        $m->set('ts', clone $m->get('ts'));

        $this->assertFalse($m->isDirty('ts'));
    }

    public function testTimestampSave(): void
    {
        $this->setDb([
            'types' => [
                [
                    'date' => '',
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'date']);
        $m = $m->loadOne();
        $m->set('ts', new \DateTime('2012-02-30'));
        $m->save();

        // stores valid date.
        $this->assertEquals(['types' => [1 => ['id' => 1, 'date' => '2012-03-01']]], $this->getDb());
    }

    public function testIntegerSave(): void
    {
        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('i', ['type' => 'integer']);
        $m = $m->createEntity();

        $m->getDataRef()['i'] = 1;
        $this->assertSame([], $m->getDirtyRef());

        $m->set('i', '1');
        $this->assertSame([], $m->getDirtyRef());

        $m->set('i', '2');
        $this->assertSame(['i' => 1], $m->getDirtyRef());

        $m->set('i', '1');
        $this->assertSame([], $m->getDirtyRef());

        // same test without type integer
        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('i');
        $m = $m->createEntity();

        $m->getDataRef()['i'] = 1;
        $this->assertSame([], $m->getDirtyRef());

        $m->set('i', '1');
        $this->assertSame([], $m->getDirtyRef());

        $m->set('i', '2');
        $this->assertSame(['i' => 1], $m->getDirtyRef());

        $m->set('i', '1');
        $this->assertSame([], $m->getDirtyRef());

        $m->set('i', 1);
        $this->assertSame([], $m->getDirtyRef());
    }

    public function testDirtyTime(): void
    {
        $sqlTime = new \DateTime('11:44:08 GMT');
        $sqlTimeNew = new \DateTime('12:34:56 GMT');

        $this->setDb([
            'types' => [
                [
                    'date' => $sqlTime->format('H:i:s'),
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'time']);
        $m = $m->loadOne();

        $m->set('ts', $sqlTimeNew);
        $this->assertTrue($m->isDirty('ts'));

        $m->set('ts', $sqlTime);
        $this->assertFalse($m->isDirty('ts'));

        $m->set('ts', $sqlTimeNew);
        $this->assertTrue($m->isDirty('ts'));
    }

    public function testDirtyTimeAfterSave(): void
    {
        $sqlTime = new \DateTime('11:44:08 GMT');
        $sqlTimeNew = new \DateTime('12:34:56 GMT');

        $this->setDb([
            'types' => [
                [
                    'date' => null,
                ],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'time']);
        $m = $m->loadOne();

        $m->set('ts', $sqlTime);
        $this->assertTrue($m->isDirty('ts'));

        $m->save();
        $m->reload();

        $m->set('ts', $sqlTime);
        $this->assertFalse($m->isDirty('ts'));

        $m->set('ts', $sqlTimeNew);
        $this->assertTrue($m->isDirty('ts'));
    }
}
