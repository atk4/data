<?php

namespace atk4\data\tests;

use atk4\core\Exception;
use atk4\data\Model;
use atk4\data\Persistence_Static;
use atk4\data\UserAction;

/**
 * Sample trait designed to extend model.
 *
 * @target Model
 */
trait ACReminder
{
    public function send_reminder()
    {
        $this->save(['reminder_sent' => true]);

        return 'sent reminder to '.$this->getTitle();
    }

    public function backup_clients()
    {
        return 'backs up all clients';
    }
}

class ACClient extends Model
{
    use ACReminder;

    public function init()
    {
        parent::init();

        $this->addField('name');
        $this->addField('reminder_sent', ['type'=>'boolean']);

        // this action can be invoked from UI
        $this->addAction('send_reminder');

        // this action will be system action, so it will not be invokable from UI
        $this->addAction('backup_clients', ['scope' => UserAction\Generic::ALL_RECORDS, 'system' => true]);
    }
}

/**
 * Implements various tests for UserAction.
 */
class UserActionTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public $pers = null;

    public function setUp()
    {
        parent::setUp();

        $this->pers = new Persistence_Static([
            1 => ['name'=>'John'],
            2 => ['name'=>'Peter'],
        ]);
    }

    public function testBasic()
    {
        $client = new ACClient($this->pers);

        $actions = $client->getActions();
        $this->assertEquals(1, count($actions)); // don't return system actions here
        $this->assertEquals(0, count($client->getActions(UserAction\Generic::ALL_RECORDS))); // don't return system actions here

        $act1 = $actions['send_reminder'];

        // action takes no arguments. If it would, we should be able to find info about those
        $this->assertEquals([], $act1->args);
        $this->assertEquals(UserAction\Generic::SINGLE_RECORD, $act1->scope);

        // load record, before executing, because scope is single record
        $client->load(1);

        $this->assertNotTrue($client['reminder_sent']);
        $res = $act1->execute();
        $this->assertTrue($client['reminder_sent']);

        $this->assertEquals('sent reminder to John', $res);
        $client->unload();

        // test system action
        $act2 = $client->getAction('backup_clients');

        // action takes no arguments. If it would, we should be able to find info about those
        $this->assertEquals([], $act2->args);
        $this->assertEquals(UserAction\Generic::ALL_RECORDS, $act2->scope);

        $res = $act2->execute();
        $this->assertEquals('backs up all clients', $res);

        // non-existing action
        $act3 = $client->hasAction('foo');
        $this->assertFalse($act3);
    }

    public function testPreview()
    {
        $client = new ACClient($this->pers);
        $client->addAction('say_name', function ($m) {
            return $m['name'];
        });

        $client->load(1);
        $this->assertEquals('John', $client->getAction('say_name')->execute());

        $client->getAction('say_name')->preview = function ($m, $arg) {
            return ($m instanceof ACClient) ? 'will say '.$m['name'] : 'will fail';
        };
        $this->assertEquals('will say John', $client->getAction('say_name')->preview('x'));

        $client->addAction('also_backup', ['callback'=>'backup_clients']);
        $this->assertEquals('backs up all clients', $client->getAction('also_backup')->execute());

        $client->getAction('also_backup')->preview = 'backup_clients';
        $this->assertEquals('backs up all clients', $client->getAction('also_backup')->preview());

        $this->assertEquals('Will execute Also Backup', $client->getAction('also_backup')->getDescription());
    }

    public function testPreviewFail()
    {
        $this->expectExceptionMessage('specify preview callback');
        $client = new ACClient($this->pers);
        $client->getAction('backup_clients')->preview();
    }

    public function testScope1()
    {
        $client = new ACClient($this->pers);
        $this->expectExceptionMessage('load existing record');
        $client->executeAction('send_reminder');
    }

    public function testScope2()
    {
        $client = new ACClient($this->pers);
        $client->addAction('new_client', ['scope'=>UserAction\Generic::NO_RECORDS]);
        $client->load(1);

        $this->expectExceptionMessage('executed on existing record');
        $client->executeAction('new_client');
    }

    public function testScope3()
    {
        $client = new ACClient($this->pers);
        $client->addAction('new_client', ['scope'=>UserAction\Generic::NO_RECORDS, 'atomic'=>false]);

        $this->expectExceptionMessage('not defined');
        $client->executeAction('new_client');
    }

    public function testException1()
    {
        $this->expectExceptionMessage('not found');
        $client = new ACClient($this->pers);
        $client->getAction('non_existant_action');
    }

    public function testDisabled1()
    {
        $client = new ACClient($this->pers);
        $client->load(1);

        $client->getAction('send_reminder')->enabled = false;

        $this->expectExceptionMessage('disabled');
        $client->getAction('send_reminder')->execute();
    }

    public function testDisabled2()
    {
        $client = new ACClient($this->pers);
        $client->load(1);

        $client->getAction('send_reminder')->enabled = function () {
            return false;
        };

        $this->expectExceptionMessage('disabled');
        $client->getAction('send_reminder')->execute();
    }

    public function testDisabled3()
    {
        $client = new ACClient($this->pers);
        $client->load(1);

        $client->getAction('send_reminder')->enabled = function () {
            return true;
        };

        $client->getAction('send_reminder')->execute();
    }

    public function testFields()
    {
        try {
            $client = new ACClient($this->pers);
            $a = $client->addAction('change_details', ['callback' => 'save', 'fields' => ['name']]);

            $client->load(1);

            $this->assertNotEquals('Peter', $client['name']);
            $client['name'] = 'Peter';
            $a->execute();
            $this->assertEquals('Peter', $client['name']);
        } catch (Exception $e) {
            echo $e->getColorfulText();

            throw $e;
        }
    }

    public function testFieldsTooDirty1()
    {
        $client = new ACClient($this->pers);
        $a = $client->addAction('change_details', ['callback'=>'save', 'fields'=>['name']]);

        $client->load(1);

        $this->assertNotEquals('Peter', $client['name']);
        $client['name'] = 'Peter';
        $client['reminder_sent'] = true;
        $this->expectExceptionMessage('dirty fields');
        $a->execute();
        $this->assertEquals('Peter', $client['name']);
    }

    public function testFieldsIncorrect()
    {
        $client = new ACClient($this->pers);
        $a = $client->addAction('change_details', ['callback'=>'save', 'fields'=>'whops_forgot_brackets']);

        $client->load(1);

        $this->assertNotEquals('Peter', $client['name']);
        $client['name'] = 'Peter';
        $this->expectExceptionMessage('array');
        $a->execute();
        $this->assertEquals('Peter', $client['name']);
    }
}
