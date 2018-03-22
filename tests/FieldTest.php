<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

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
        $db = new Persistence_SQL($this->db->connection);
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
        $db = new Persistence_SQL($this->db->connection);
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
    public function testMandatory4()
    {
        $db = new Persistence_SQL($this->db->connection);
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

    public function testMandatory3()
    {
        if ($this->driver == 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $db = new Persistence_SQL($this->db->connection);
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
        $db = new Persistence_SQL($this->db->connection);
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
        $m->getElement('surname')->never_save = false;
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

        $db = new Persistence_SQL($this->db->connection);
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

        $db = new Persistence_SQL($this->db->connection);
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

    public function testSystem1()
    {
        $m = new Model();
        $m->addField('foo', ['system' => true]);
        $m->addField('bar');
        $this->assertEquals(false, $m->getElement('foo')->isEditable());
        $this->assertEquals(false, $m->getElement('foo')->isVisible());

        $m->onlyFields(['bar']);
        // TODO: build a query and see if the field is there
    }

    public function testEncryptedField()
    {
        $db = new Persistence_SQL($this->db->connection);
        $a = [
            'user' => [
                '_' => ['id' => 1, 'name' => 'John', 'secret' => 'Smith'],
            ], ];
        $this->setDB($a);

        $encrypt = function ($value, $field, $persistence) {
            if (!$persistence instanceof \atk4\data\Persistence_SQL) {
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
            if (!$persistence instanceof \atk4\data\Persistence_SQL) {
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
}
