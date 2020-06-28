<?php

declare(strict_types=1);

namespace atk4\data\tests;

use atk4\core\Exception;
use atk4\data\Model;
use atk4\data\Persistence\Static_ as Persistence_Static;

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

        return 'sent reminder to ' . $this->getTitle();
    }

    public function backup_clients()
    {
        return 'backs up all clients';
    }
}

class ACClient extends Model
{
    use ACReminder;

    public function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('reminder_sent', ['type' => 'boolean']);

        // this action can be invoked from UI
        $this->addAction('send_reminder');

        // this action will be system action, so it will not be invokable from UI
        $this->addAction('backup_clients', ['scope' => Model\UserAction::SCOPE_ALL, 'system' => true]);
    }
}

/**
 * Implements various tests for UserAction.
 */
class UserActionTest extends \atk4\schema\PhpunitTestCase
{
    public $pers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pers = new Persistence_Static([
            1 => ['name' => 'John'],
            2 => ['name' => 'Peter'],
        ]);
    }

    public function testBasic()
    {
        $client = new ACClient($this->pers);

        $actions = $client->getUserActions();
        $this->assertSame(4, count($actions)); // don't return system actions here, but include add/edit/delete
        $this->assertSame(0, count($client->getUserActions(Model\UserAction::SCOPE_ALL))); // don't return system actions here

        $act1 = $actions['send_reminder'];

        // action takes no arguments. If it would, we should be able to find info about those
        $this->assertSame([], $act1->args);
        $this->assertSame(Model\UserAction::SCOPE_SINGLE, $act1->scope);

        // load record, before executing, because scope is single record
        $client->load(1);

        $this->assertNotTrue($client->get('reminder_sent'));
        $res = $act1->execute();
        $this->assertTrue($client->get('reminder_sent'));

        $this->assertSame('sent reminder to John', $res);
        $client->unload();

        // test system action
        $act2 = $client->getUserAction('backup_clients');

        // action takes no arguments. If it would, we should be able to find info about those
        $this->assertSame([], $act2->args);
        $this->assertSame(Model\UserAction::SCOPE_ALL, $act2->scope);

        $res = $act2->execute();
        $this->assertSame('backs up all clients', $res);

        // non-existing action
        $this->assertFalse($client->hasAction('foo'));
    }

    public function testPreview()
    {
        $client = new ACClient($this->pers);
        $client->addAction('say_name', function ($m) {
            return $m->get('name');
        });

        $client->load(1);
        $this->assertSame('John', $client->getUserAction('say_name')->execute());

        $client->getUserAction('say_name')->preview = function ($m, $arg) {
            return ($m instanceof ACClient) ? 'will say ' . $m->get('name') : 'will fail';
        };
        $this->assertSame('will say John', $client->getUserAction('say_name')->preview('x'));

        $client->addAction('also_backup', ['callback' => 'backup_clients']);
        $this->assertSame('backs up all clients', $client->getUserAction('also_backup')->execute());

        $client->getUserAction('also_backup')->preview = 'backup_clients';
        $this->assertSame('backs up all clients', $client->getUserAction('also_backup')->preview());

        $this->assertSame('Will execute Also Backup', $client->getUserAction('also_backup')->getDescription());
    }

    public function testPreviewFail()
    {
        $this->expectExceptionMessage('specify preview callback');
        $client = new ACClient($this->pers);
        $client->getUserAction('backup_clients')->preview();
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
        $client->addAction('new_client', ['scope' => Model\UserAction::SCOPE_NONE]);
        $client->load(1);

        $this->expectExceptionMessage('executed on existing record');
        $client->executeAction('new_client');
    }

    public function testScope3()
    {
        $client = new ACClient($this->pers);
        $client->addAction('new_client', ['scope' => Model\UserAction::SCOPE_NONE, 'atomic' => false]);

        $this->expectExceptionMessage('not defined');
        $client->executeAction('new_client');
    }

    public function testException1()
    {
        $this->expectException(\atk4\core\Exception::class);
        $client = new ACClient($this->pers);
        $client->getUserAction('non_existant_action');
    }

    public function testDisabled1()
    {
        $client = new ACClient($this->pers);
        $client->load(1);

        $client->getUserAction('send_reminder')->enabled = false;

        $this->expectExceptionMessage('disabled');
        $client->getUserAction('send_reminder')->execute();
    }

    public function testDisabled2()
    {
        $client = new ACClient($this->pers);
        $client->load(1);

        $client->getUserAction('send_reminder')->enabled = function () {
            return false;
        };

        $this->expectExceptionMessage('disabled');
        $client->getUserAction('send_reminder')->execute();
    }

    public function testDisabled3()
    {
        $client = new ACClient($this->pers);
        $client->load(1);

        $client->getUserAction('send_reminder')->enabled = function () {
            return true;
        };

        $client->getUserAction('send_reminder')->execute();
        $this->assertTrue(true); // no exception
    }

    public function testFields()
    {
        try {
            $client = new ACClient($this->pers);
            $a = $client->addAction('change_details', ['callback' => 'save', 'fields' => ['name']]);

            $client->load(1);

            $this->assertNotSame('Peter', $client->get('name'));
            $client->set('name', 'Peter');
            $a->execute();
            $this->assertSame('Peter', $client->get('name'));
        } catch (Exception $e) {
            echo $e->getColorfulText();

            throw $e;
        }
    }

    public function testFieldsTooDirty1()
    {
        $client = new ACClient($this->pers);
        $a = $client->addAction('change_details', ['callback' => 'save', 'fields' => ['name']]);

        $client->load(1);

        $this->assertNotSame('Peter', $client->get('name'));
        $client->set('name', 'Peter');
        $client->set('reminder_sent', true);
        $this->expectExceptionMessage('dirty fields');
        $a->execute();
        $this->assertSame('Peter', $client->get('name'));
    }

    public function testFieldsIncorrect()
    {
        $client = new ACClient($this->pers);
        $a = $client->addAction('change_details', ['callback' => 'save', 'fields' => 'whops_forgot_brackets']);

        $client->load(1);

        $this->assertNotSame('Peter', $client->get('name'));
        $client->set('name', 'Peter');
        $this->expectExceptionMessage('array');
        $a->execute();
        $this->assertSame('Peter', $client->get('name'));
    }

    public function testConfirmation()
    {
        $client = new ACClient($this->pers);
        $client->load(1);
        $action = $client->addAction('test');

        $this->assertFalse($action->getConfirmation());

        $action->confirmation = true;
        $this->assertSame('Are you sure you wish to Test John?', $action->getConfirmation());

        $action->confirmation = 'Are you sure?';
        $this->assertSame('Are you sure?', $action->getConfirmation());
    }
}
