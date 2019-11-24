<?php

namespace atk4\data\tests;

use atk4\core\Exception;
use atk4\data\Field;
use atk4\data\Model;
use atk4\data\Persistence;

class FieldTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testDirty1()
    {
        $m = new Model();
        $m->addField('foo', ['default' => 'abc']);

        $this->assertEquals(false, $m->isDirty('foo'));

        $m['foo'] = 'abc';
        $this->assertEquals(false, $m->isDirty('foo'));

        $m['foo'] = 'bca';
        $this->assertEquals(true, $m->isDirty('foo'));

        $m['foo'] = 'abc';
        $this->assertEquals(false, $m->isDirty('foo'));

        // set initial data
        $m->data['foo'] = 'xx';
        $this->assertEquals(false, $m->isDirty('foo'));

        $m['foo'] = 'abc';
        $this->assertEquals(true, $m->isDirty('foo'));

        $m['foo'] = 'bca';
        $this->assertEquals(true, $m->isDirty('foo'));

        $m['foo'] = 'xx';
        $this->assertEquals(false, $m->isDirty('foo'));
    }

    public function testCompare()
    {
        $m = new Model();
        $m->addField('foo', ['default' => 'abc']);

        $this->assertEquals(true, $m->compare('foo', 'abc'));
        $m['foo'] = 'zzz';

        $this->assertEquals(false, $m->compare('foo', 'abc'));
        $this->assertEquals(true, $m->compare('foo', 'zzz'));
    }

    public function testMandatory1()
    {
        $m = new Model();
        $m->addField('foo', ['mandatory' => true]);
        $m['foo'] = 'abc';
        $m['foo'] = null;
        $m['foo'] = '';
        unset($m['foo']);
    }

    /**
     * @expectedException Exception
     */
    public function testRequired1()
    {
        $m = new Model();
        $m->addField('foo', ['required' => true]);
        $m['foo'] = '';
        unset($m['foo']);
    }

    /**
     * @expectedException Exception
     */
    public function testRequired1_1()
    {
        $m = new Model();
        $m->addField('foo', ['required' => true]);
        $m['foo'] = null;
        unset($m['foo']);
    }

    /**
     * @expectedException Exception
     */
    public function testMandatory2()
    {
        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ], ];
        $this->setDB($a);

        $m = new Model($db, 'user');
        $m->addField('name', ['mandatory' => true]);
        $m->addField('surname');
        $m->insert(['surname' => 'qq']);
    }

    /**
     * @expectedException Exception
     */
    public function testRequired2()
    {
        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ], ];
        $this->setDB($a);

        $m = new Model($db, 'user');
        $m->addField('name', ['required' => true]);
        $m->addField('surname');
        $m->insert(['surname' => 'qq', 'name'=>'']);
    }

    /**
     * @expectedException Exception
     */
    public function testMandatory3()
    {
        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ], ];
        $this->setDB($a);

        $m = new Model($db, 'user');
        $m->addField('name', ['mandatory' => true]);
        $m->addField('surname');
        $m->load(1);
        $m->save(['name' => null]);
    }

    public function testMandatory4()
    {
        if ($this->driver == 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ], ];
        $this->setDB($a);

        $m = new Model($db, 'user');
        $m->addField('name', ['mandatory' => true, 'default' => 'NoName']);
        $m->addField('surname');
        $m->insert(['surname' => 'qq']);
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
                2 => ['id' => 2, 'name' => 'NoName', 'surname' => 'qq'],
            ], ];
        $this->assertEquals($a, $this->getDB());
    }

    public function testCaption()
    {
        $m = new Model();
        $f = $m->addField('foo');
        $this->assertEquals('Foo', $f->getCaption());

        $f = $m->addField('user_defined_entity');
        $this->assertEquals('User Defined Entity', $f->getCaption());

        $f = $m->addField('foo2', ['caption'=>'My Foo']);
        $this->assertEquals('My Foo', $f->getCaption());

        $f = $m->addField('foo3', ['ui'=>['caption'=>'My Foo']]);
        $this->assertEquals('My Foo', $f->getCaption());
    }

    /**
     * @expectedException Exception
     */
    public function testReadOnly1()
    {
        $m = new Model();
        $m->addField('foo', ['read_only' => true]);
        $m['foo'] = 'bar';
    }

    public function testReadOnly2()
    {
        $m = new Model();
        $m->addField('foo', ['read_only' => true, 'default' => 'abc']);
        $m['foo'] = 'abc';
    }

    public function testReadOnly3()
    {
        $m = new Model();
        $m->addField('foo', ['read_only' => true, 'default' => 'abc']);
        $m->data['foo'] = 'xx';
        $m['foo'] = 'xx';
    }

    /**
     * @expectedException Exception
     */
    public function testEnum1()
    {
        $m = new Model();
        $m->addField('foo', ['enum' => ['foo', 'bar']]);
        $m['foo'] = 'xx';
    }

    public function testEnum2()
    {
        $m = new Model();
        $m->addField('foo', ['enum' => [1, 'bar']]);
        $m['foo'] = 1;

        $this->assertSame(1, $m['foo']);

        $m['foo'] = 'bar';
        $this->assertSame('bar', $m['foo']);
    }

    /**
     * @expectedException Exception
     */
    public function testEnum3()
    {
        $m = new Model();
        $m->addField('foo', ['enum' => [1, 'bar']]);
        $m['foo'] = true;
    }

    public function testEnum4()
    {
        // PHP type control is really crappy...
        // This test has no purpose but it stands testament
        // to a weird behaviours of PHP
        $m = new Model();
        $m->addField('foo', ['enum' => [1, 'bar'], 'default' => 1]);
        $m['foo'] = null;

        $this->assertSame(null, $m['foo']);
    }

    /**
     * @expectedException Exception
     */
    public function testValues1()
    {
        $m = new Model();
        $m->addField('foo', ['values' => ['foo', 'bar']]);
        $m['foo'] = 4;
    }

    public function testValues2()
    {
        $m = new Model();
        $m->addField('foo', ['values' => [3=>'bar']]);
        $m['foo'] = 3;

        $this->assertSame(3, $m['foo']);

        $m['foo'] = null;
        $this->assertSame(null, $m['foo']);
    }

    /**
     * @expectedException Exception
     */
    public function testValues3()
    {
        $m = new Model();
        $m->addField('foo', ['values' => [1=>'bar']]);
        $m['foo'] = true;
    }

    /**
     * @expectedException Exception
     */
    public function testValues3a()
    {
        $m = new Model();
        $m->addField('foo', ['values' => [1=>'bar']]);
        $m['foo'] = 'bar';
    }

    public function testValues4()
    {
        // PHP type control is really crappy...
        // This test has no purpose but it stands testament
        // to a weird behaviours of PHP
        $m = new Model();
        $m->addField('foo', ['values' => ['1a'=>'bar']]);
        $m['foo'] = '1a';
    }

    public function testPersist()
    {
        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ], ];
        $this->setDB($a);

        $m = new Model($db, 'item');
        $m->addField('name', ['never_persist' => true]);
        $m->addField('surname', ['never_save' => true]);
        $m->load(1);

        $this->assertNull($m['name']);
        $this->assertEquals('Smith', $m['surname']);

        $m['name'] = 'Bill';
        $m['surname'] = 'Stalker';
        $m->save();
        $this->assertEquals($a, $this->getDB());

        $m->reload();
        $this->assertEquals('Smith', $m['surname']);
        $m->getField('surname')->never_save = false;
        $m['surname'] = 'Stalker';
        $m->save();
        $a['item'][1]['surname'] = 'Stalker';
        $this->assertEquals($a, $this->getDB());

        $m->addHook('beforeSave', function ($m) {
            if ($m->isDirty('name')) {
                $m['surname'] = $m['name'];
                unset($m['name']);
            } elseif ($m->isDirty('surname')) {
                $m['name'] = $m['surname'];
                unset($m['surname']);
            }
        });

        $m['name'] = 'X';
        $m->save();

        $a['item'][1]['surname'] = 'X';

        $this->assertEquals($a, $this->getDB());
        $this->assertNull($m['name']);
        $this->assertEquals('X', $m['surname']);

        $m['surname'] = 'Y';
        $m->save();

        $this->assertEquals($a, $this->getDB());
        $this->assertEquals('Y', $m['name']);
        $this->assertEquals('X', $m['surname']);
    }

    public function testTitle()
    {
        if ($this->driver == 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'category_id' => 2],
            ],
            'category' => [
                1 => ['id' => 1, 'name' => 'General'],
                2 => ['id' => 2, 'name' => 'Programmer'],
                3 => ['id' => 3, 'name' => 'Sales'],
            ],
        ];
        $this->setDB($a);

        $c = new Model($db, 'category');
        $c->addField('name');

        $m = new Model($db, 'user');
        $m->addField('name');
        $m->hasOne('category_id', $c)
            ->addTitle();

        $m->load(1);

        $this->assertEquals('John', $m['name']);
        $this->assertEquals('Programmer', $m['category']);

        $m->insert(['Peter', 'category' => 'Sales']);

        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'category_id' => 2],
                2 => ['id' => 2, 'name' => 'Peter', 'surname' => null, 'category_id' => 3],
            ],
            'category' => [
                1 => ['id' => 1, 'name' => 'General'],
                2 => ['id' => 2, 'name' => 'Programmer'],
                3 => ['id' => 3, 'name' => 'Sales'],
            ],
        ];
        $this->assertEquals($a, $this->getDB());
    }

    /**
     * @expectedException Exception
     */
    public function testStrictException1()
    {
        $m = new Model();
        $m->addField('foo');
        $m['baz'] = 'bar';
    }

    public function testStrict1()
    {
        $m = new Model(['strict_field_check' => false]);
        $m->addField('foo');
        $m['baz'] = 'bar';
    }

    public function testActual()
    {
        if ($this->driver == 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ], ];
        $this->setDB($a);

        $m = new Model($db, 'user');
        $m->addField('first_name', ['actual' => 'name']);
        $m->addField('surname');
        $m->insert(['first_name' => 'Peter', 'surname' => 'qq']);
        $m->loadBy('first_name', 'John');
        $this->assertEquals('John', $m['first_name']);

        $d = $m->export();
        $this->assertEquals('John', $d[0]['first_name']);

        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
                2 => ['id' => 2, 'name' => 'Peter', 'surname' => 'qq'],
            ], ];
        $this->assertEquals($a, $this->getDB());

        $m['first_name'] = 'Scott';
        $m->save();

        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'Scott', 'surname' => 'Smith'],
                2 => ['id' => 2, 'name' => 'Peter', 'surname' => 'qq'],
            ], ];
        $this->assertEquals($a, $this->getDB());
    }

    public function testCalculatedField()
    {
        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'invoice' => [
                1 => ['id' => 1, 'net' => 100, 'vat' => 21],
            ], ];
        $this->setDB($a);

        $m = new Model($db, 'invoice');
        $m->addField('net', ['type' => 'money']);
        $m->addField('vat', ['type' => 'money']);
        $m->addCalculatedField('total', function ($m) {
            return $m['net'] + $m['vat'];
        });
        $m->insert(['net' => 30, 'vat' => 8]);

        $m->load(1);
        $this->assertEquals(121, $m['total']);
        $m->load(2);
        $this->assertEquals(38, $m['total']);

        $d = $m->export(); // in export calculated fields are not included
        $this->assertFalse(isset($d[0]['total']));
    }

    public function testSystem1()
    {
        $m = new Model();
        $m->addField('foo', ['system' => true]);
        $m->addField('bar');
        $this->assertEquals(false, $m->getField('foo')->isEditable());
        $this->assertEquals(false, $m->getField('foo')->isVisible());

        $m->onlyFields(['bar']);
        // TODO: build a query and see if the field is there
    }

    public function testEncryptedField()
    {
        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'user' => [
                '_' => ['id' => 1, 'name' => 'John', 'secret' => 'Smith'],
            ], ];
        $this->setDB($a);

        $encrypt = function ($value, $field, $persistence) {
            if (!$persistence instanceof Persistence\SQL) {
                return $value;
            }

            /*
            $algorithm = 'rijndael-128';
            $key = md5($field->password, true);
            $iv_length = mcrypt_get_iv_size( $algorithm, MCRYPT_MODE_CBC );
            $iv = mcrypt_create_iv( $iv_length, MCRYPT_RAND );
            return mcrypt_encrypt( $algorithm, $key, $value, MCRYPT_MODE_CBC, $iv );
             */
            return base64_encode($value);
        };

        $decrypt = function ($value, $field, $persistence) {
            if (!$persistence instanceof Persistence\SQL) {
                return $value;
            }

            /*
            $algorithm = 'rijndael-128';
            $key = md5($field->password, true);
            $iv_length = mcrypt_get_iv_size( $algorithm, MCRYPT_MODE_CBC );
            $iv = mcrypt_create_iv( $iv_length, MCRYPT_RAND );
            return mcrypt_encrypt( $algorithm, $key, $value, MCRYPT_MODE_CBC, $iv );
             */
            return base64_decode($value);
        };

        $m = new Model($db, 'user');
        $m->addField('name', ['mandatory' => true]);
        $m->addField('secret', [
            //'password'  => 'bonkers',
            'typecast'  => [$encrypt, $decrypt],
        ]);
        $m->save(['name' => 'John', 'secret' => 'i am a woman']);

        $a = $this->getDB();
        $this->assertNotNull($a['user'][1]['secret']);
        $this->assertNotEquals('i am a woman', $a['user'][1]['secret']);

        $m->unload()->load(1);
        $this->assertEquals('i am a woman', $m['secret']);
    }

    public function testNormalize()
    {
        $m = new Model(['strict_types' => true]);

        // Field types: 'string', 'text', 'integer', 'money', 'float', 'boolean',
        //              'date', 'datetime', 'time', 'array', 'object'
        $m->addField('string', ['type' => 'string']);
        $m->addField('text', ['type' => 'text']);
        $m->addField('integer', ['type' => 'integer']);
        $m->addField('money', ['type' => 'money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('boolean_enum', ['type' => 'boolean', 'enum'=>['N', 'Y']]);
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('array', ['type' => 'array']);
        $m->addField('object', ['type' => 'object']);

        // string
        $m['string'] = "Two\r\nLines  ";
        $this->assertSame('TwoLines', $m['string']);

        $m['string'] = "Two\rLines  ";
        $this->assertSame('TwoLines', $m['string']);

        $m['string'] = "Two\nLines  ";
        $this->assertSame('TwoLines', $m['string']);

        // text
        $m['text'] = "Two\r\nLines  ";
        $this->assertSame("Two\nLines", $m['text']);

        $m['text'] = "Two\rLines  ";
        $this->assertSame("Two\nLines", $m['text']);

        $m['text'] = "Two\nLines  ";
        $this->assertSame("Two\nLines", $m['text']);

        // integer, money, float
        $m['integer'] = '12,345.67676767'; // no digits after dot
        $this->assertSame(12345, $m['integer']);

        $m['money'] = '12,345.67676767'; // 4 digits after dot
        $this->assertSame(12345.6768, $m['money']);

        $m['float'] = '12,345.67676767'; // don't round
        $this->assertSame(12345.67676767, $m['float']);

        // boolean
        $m['boolean'] = 0;
        $this->assertSame(false, $m['boolean']);
        $m['boolean'] = 1;
        $this->assertSame(true, $m['boolean']);

        $m['boolean_enum'] = 'N';
        $this->assertSame(false, $m['boolean_enum']);
        $m['boolean_enum'] = 'Y';
        $this->assertSame(true, $m['boolean_enum']);

        // date, datetime, time
        $m['date'] = 123;
        $this->assertInstanceof('DateTime', $m['date']);
        $m['date'] = '123';
        $this->assertInstanceof('DateTime', $m['date']);
        $m['date'] = '2018-05-31';
        $this->assertInstanceof('DateTime', $m['date']);
        $m['datetime'] = 123;
        $this->assertInstanceof('DateTime', $m['datetime']);
        $m['datetime'] = '123';
        $this->assertInstanceof('DateTime', $m['datetime']);
        $m['datetime'] = '2018-05-31 12:13:14';
        $this->assertInstanceof('DateTime', $m['datetime']);
        $m['time'] = 123;
        $this->assertInstanceof('DateTime', $m['time']);
        $m['time'] = '123';
        $this->assertInstanceof('DateTime', $m['time']);
        $m['time'] = '12:13:14';
        $this->assertInstanceof('DateTime', $m['time']);
    }

    /**
     * @expectedException \atk4\data\ValidationException
     */
    public function testNormalizeException1()
    {
        $m = new Model(['strict_types' => true]);
        $m->addField('foo', ['type' => 'string']);
        $m['foo'] = [];
    }

    /**
     * @expectedException \atk4\data\ValidationException
     */
    public function testNormalizeException2()
    {
        $m = new Model(['strict_types' => true]);
        $m->addField('foo', ['type' => 'text']);
        $m['foo'] = [];
    }

    /**
     * @expectedException \atk4\data\ValidationException
     */
    public function testNormalizeException3()
    {
        $m = new Model(['strict_types' => true]);
        $m->addField('foo', ['type' => 'integer']);
        $m['foo'] = [];
    }

    /**
     * @expectedException \atk4\data\ValidationException
     */
    public function testNormalizeException4()
    {
        $m = new Model(['strict_types' => true]);
        $m->addField('foo', ['type' => 'money']);
        $m['foo'] = [];
    }

    /**
     * @expectedException \atk4\data\ValidationException
     */
    public function testNormalizeException5()
    {
        $m = new Model(['strict_types' => true]);
        $m->addField('foo', ['type' => 'float']);
        $m['foo'] = [];
    }

    /**
     * @expectedException \atk4\data\ValidationException
     */
    public function testNormalizeException6()
    {
        $m = new Model(['strict_types' => true]);
        $m->addField('foo', ['type' => 'date']);
        $m['foo'] = [];
    }

    /**
     * @expectedException \atk4\data\ValidationException
     */
    public function testNormalizeException7()
    {
        $m = new Model(['strict_types' => true]);
        $m->addField('foo', ['type' => 'datetime']);
        $m['foo'] = [];
    }

    /**
     * @expectedException \atk4\data\ValidationException
     */
    public function testNormalizeException8()
    {
        $m = new Model(['strict_types' => true]);
        $m->addField('foo', ['type' => 'time']);
        $m['foo'] = [];
    }

    /**
     * @expectedException \atk4\data\ValidationException
     */
    public function testNormalizeException9()
    {
        $m = new Model(['strict_types' => true]);
        $m->addField('foo', ['type' => 'integer']);
        $m['foo'] = '123---456';
    }

    /**
     * @expectedException \atk4\data\ValidationException
     */
    public function testNormalizeException10()
    {
        $m = new Model(['strict_types' => true]);
        $m->addField('foo', ['type' => 'money']);
        $m['foo'] = '123---456';
    }

    /**
     * @expectedException \atk4\data\ValidationException
     */
    public function testNormalizeException11()
    {
        $m = new Model(['strict_types' => true]);
        $m->addField('foo', ['type' => 'float']);
        $m['foo'] = '123---456';
    }

    /**
     * @expectedException \atk4\data\ValidationException
     */
    public function testNormalizeException12()
    {
        $m = new Model(['strict_types' => true]);
        $m->addField('foo', ['type' => 'array']);
        $m['foo'] = 'ABC';
    }

    /**
     * @expectedException \atk4\data\ValidationException
     */
    public function testNormalizeException13()
    {
        $m = new Model(['strict_types' => true]);
        $m->addField('foo', ['type' => 'object']);
        $m['foo'] = 'ABC';
    }

    /**
     * @expectedException \atk4\data\ValidationException
     */
    public function testNormalizeException14()
    {
        $m = new Model(['strict_types' => true]);
        $m->addField('foo', ['type' => 'boolean']);
        $m['foo'] = 'ABC';
    }

    public function testToString()
    {
        $m = new Model(['strict_types' => true]);

        // Field types: 'string', 'text', 'integer', 'money', 'float', 'boolean',
        //              'date', 'datetime', 'time', 'array', 'object'
        $m->addField('string', ['type' => 'string']);
        $m->addField('text', ['type' => 'text']);
        $m->addField('integer', ['type' => 'integer']);
        $m->addField('money', ['type' => 'money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('boolean_enum', ['type' => 'boolean', 'enum'=>['N', 'Y']]);
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('array', ['type' => 'array']);
        $m->addField('object', ['type' => 'object']);

        $this->assertSame('TwoLines', $m->getField('string')->toString("Two\r\nLines  "));
        $this->assertSame("Two\nLines", $m->getField('text')->toString("Two\r\nLines  "));
        $this->assertSame('123', $m->getField('integer')->toString(123));
        $this->assertSame('123.45', $m->getField('money')->toString(123.45));
        $this->assertSame('123.456789', $m->getField('float')->toString(123.456789));
        $this->assertSame('1', $m->getField('boolean')->toString(true));
        $this->assertSame('0', $m->getField('boolean')->toString(false));
        $this->assertSame('1', $m->getField('boolean_enum')->toString('Y'));
        $this->assertSame('0', $m->getField('boolean_enum')->toString('N'));
        $this->assertSame('2019-01-20', $m->getField('date')->toString(new \DateTime('2019-01-20T12:23:34+00:00')));
        $this->assertSame('2019-01-20T12:23:34+00:00', $m->getField('datetime')->toString(new \DateTime('2019-01-20T12:23:34+00:00')));
        $this->assertSame('12:23:34', $m->getField('time')->toString(new \DateTime('2019-01-20T12:23:34+00:00')));
        $this->assertSame('{"foo":"bar","int":123,"rows":["a","b"]}', $m->getField('array')->toString(['foo'=>'bar', 'int'=>123, 'rows'=>['a', 'b']]));
        $this->assertSame('{"foo":"bar","int":123,"rows":["a","b"]}', $m->getField('object')->toString((object) ['foo'=>'bar', 'int'=>123, 'rows'=>['a', 'b']]));
    }

    public function testAddFieldDirectly()
    {
        $this->expectException(Exception::class);
        $model = new Model();
        $model->add(new Field(), 'test');
    }

    public function testGetFields()
    {
        $model = new Model();
        $model->addField('system', ['system'=>true]);
        $model->addField('editable', ['ui'=>['editable'=>true]]);
        $model->addField('editable_system', ['ui'=>['editable'=>true], 'system'=>true]);
        $model->addField('visible', ['ui'=>['visible'=>true]]);
        $model->addField('visible_system', ['ui'=>['visible'=>true], 'system'=>true]);
        $model->addField('not_editable', ['ui'=>['editable'=>false]]);

        $this->assertEquals(['system', 'editable', 'editable_system', 'visible', 'visible_system', 'not_editable'], array_keys($model->getFields()));
        $this->assertEquals(['system', 'editable_system', 'visible_system'], array_keys($model->getFields('system')));
        $this->assertEquals(['editable', 'visible', 'not_editable'], array_keys($model->getFields('not system')));
        $this->assertEquals(['editable', 'editable_system', 'visible'], array_keys($model->getFields('editable')));
        $this->assertEquals(['editable', 'visible', 'visible_system', 'not_editable'], array_keys($model->getFields('visible')));

        $model->onlyFields(['system', 'visible', 'not_editable']);

        // getFields() is unaffected by only_fields, will always return all fields
        $this->assertEquals(['system', 'editable', 'editable_system', 'visible', 'visible_system', 'not_editable'], array_keys($model->getFields()));

        // only return subset of only_fields
        $this->assertEquals(['visible', 'not_editable'], array_keys($model->getFields('visible')));

        $this->expectExceptionMessage('not supported');
        $model->getFields('foo');
    }

    public function testDateTimeFieldsToString()
    {
        $model = new Model();
        $model->addField('date', ['type' => 'date']);
        $model->addField('time', ['type' => 'time']);
        $model->addField('datetime', ['type' => 'datetime']);

        $this->assertEquals('', $model->getField('date')->toString());
        $this->assertEquals('', $model->getField('time')->toString());
        $this->assertEquals('', $model->getField('datetime')->toString());

        $current_date = new \DateTime();
        $model->set('date', $current_date);
        $model->set('time', $current_date);
        $model->set('datetime', $current_date);

        $this->assertEquals($current_date->format('Y-m-d'), $model->getField('date')->toString());
        $this->assertEquals($current_date->format('H:i:s'), $model->getField('time')->toString());
        $this->assertEquals($current_date->format('c'), $model->getField('datetime')->toString());
    }
}
