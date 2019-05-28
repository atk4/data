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

        // this action can be invoked from UI
        $this->addAction('send_reminder');

        // this action will be system action, so it will not be invokable from UI
        $a = $this->addAction('backup_clients', ['scope' => UserAction\Generic::ALL_RECORDS, 'system' => true]);
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
        $res = $act1->execute();

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
        $client->addAction('say_a', function () {
            return 'a';
        });

        $this->assertEquals('a', $client->getAction('say_a')->execute());

        $client->getAction('say_a')->preview = function ($m, $arg) {
            return ($m instanceof ACClient) ? 'will say '.$arg : 'will fail';
        };
        $this->assertEquals('will say x', $client->getAction('say_a')->preview('x'));

        $client->addAction('also_backup', ['callback'=>'backup_clients']);
        $this->assertEquals('backs up all clients', $client->getAction('also_backup')->execute());

        $client->getAction('also_backup')->preview = 'backup_clients';
        $this->assertEquals('backs up all clients', $client->getAction('also_backup')->preview());

        $this->assertEquals('Will execute Also Backup', $client->getAction('also_backup')->getDescription());
    }

    /**
     * @expectedException Exception
     */
    public function testPreviewFail()
    {
        $client = new ACClient($this->pers);
        $client->getAction('backup_clients')->preview();
    }

    /**
     * @expectedException Exception
     */
    public function testException1()
    {
        $client = new ACClient($this->pers);
        $client->getAction('non_existant_action');
    }
}
