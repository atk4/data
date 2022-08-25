<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\Exception as CoreException;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Schema\TestCase;

trait UaReminder
{
    public function sendReminder(): string
    {
        $this->save(['reminder_sent' => true]);

        return 'sent reminder to ' . $this->getTitle();
    }

    public function backupClients(): string
    {
        return 'backs up all clients';
    }
}

class UaClient extends Model
{
    use UaReminder;

    public $caption = 'UaClient';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('reminder_sent', ['type' => 'boolean']);

        // this action can be invoked from UI
        $this->addUserAction('sendReminder');

        // this action will be system action, so it will not be invokable from UI
        $this->addUserAction('backupClients', ['appliesTo' => Model\UserAction::APPLIES_TO_ALL_RECORDS, 'system' => true]);
    }
}

class UserActionTest extends TestCase
{
    /** @var Persistence\Static_ */
    public $pers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pers = new Persistence\Static_([
            1 => ['name' => 'John'],
            2 => ['name' => 'Peter'],
        ]);
    }

    public function testBasic(): void
    {
        $client = new UaClient($this->pers);

        static::assertCount(4, $client->getUserActions()); // don't return system actions here, but include add/edit/delete
        static::assertCount(0, $client->getUserActions(Model\UserAction::APPLIES_TO_ALL_RECORDS)); // don't return system actions here

        // action takes no arguments. If it would, we should be able to find info about those
        $act1 = $client->getUserActions()['sendReminder'];
        static::assertSame([], $act1->args);
        static::assertSame(Model\UserAction::APPLIES_TO_SINGLE_RECORD, $act1->appliesTo);

        // load record, before executing, because scope is single record
        $client = $client->load(1);

        $act1 = $client->getModel()->getUserActions()['sendReminder'];
        $act1 = $act1->getActionForEntity($client);
        static::assertNotTrue($client->get('reminder_sent'));
        $res = $act1->execute();
        static::assertTrue($client->get('reminder_sent'));

        static::assertSame('sent reminder to John', $res);
        $client->unload();

        // test system action
        $act2 = $client->getUserAction('backupClients');

        // action takes no arguments. If it would, we should be able to find info about those
        static::assertSame([], $act2->args);
        static::assertSame(Model\UserAction::APPLIES_TO_ALL_RECORDS, $act2->appliesTo);

        $res = $act2->execute();
        static::assertSame('backs up all clients', $res);

        // non-existing action
        static::assertFalse($client->hasUserAction('foo'));
    }

    public function testPreview(): void
    {
        $client = new UaClient($this->pers);
        $client->addUserAction('say_name', function ($m) {
            return $m->get('name');
        });

        $client = $client->load(1);
        static::assertSame('John', $client->getUserAction('say_name')->execute());

        $client->getUserAction('say_name')->preview = function ($m, $arg) {
            return ($m instanceof UaClient) ? 'will say ' . $m->get('name') : 'will fail';
        };
        static::assertSame('will say John', $client->getUserAction('say_name')->preview('x'));

        $client->getModel()->addUserAction('also_backup', ['callback' => 'backupClients']);
        static::assertSame('backs up all clients', $client->getUserAction('also_backup')->execute());

        $client->getUserAction('also_backup')->preview = 'backupClients';
        static::assertSame('backs up all clients', $client->getUserAction('also_backup')->preview());

        static::assertSame('Also Backup UaClient', $client->getUserAction('also_backup')->getDescription());
    }

    public function testPreviewFail(): void
    {
        $client = new UaClient($this->pers);

        $this->expectExceptionMessage('specify preview callback');
        $client->getUserAction('backupClients')->preview();
    }

    public function testAppliesTo1(): void
    {
        $client = new UaClient($this->pers);
        $client = $client->createEntity();

        $this->expectExceptionMessage('load existing record');
        $client->executeUserAction('sendReminder');
    }

    public function testAppliesTo2(): void
    {
        $client = new UaClient($this->pers);
        $client->addUserAction('new_client', ['appliesTo' => Model\UserAction::APPLIES_TO_NO_RECORDS]);
        $client = $client->load(1);

        $this->expectExceptionMessage('can be executed on non-existing record');
        $client->executeUserAction('new_client');
    }

    public function testAppliesTo3(): void
    {
        $client = new UaClient($this->pers);
        $client->addUserAction('new_client', ['appliesTo' => Model\UserAction::APPLIES_TO_NO_RECORDS, 'atomic' => false]);
        $client = $client->createEntity();

        $this->expectExceptionMessage('undefined method');
        $client->executeUserAction('new_client');
    }

    public function testException1(): void
    {
        $client = new UaClient($this->pers);

        $this->expectException(CoreException::class);
        $client->getUserAction('non_existant_action');
    }

    public function testDisabled1(): void
    {
        $client = new UaClient($this->pers);
        $client = $client->load(1);

        $client->getUserAction('sendReminder')->enabled = false;

        $this->expectExceptionMessage('disabled');
        $client->getUserAction('sendReminder')->execute();
    }

    public function testDisabled2(): void
    {
        $client = new UaClient($this->pers);
        $client = $client->load(1);

        $client->getUserAction('sendReminder')->enabled = function () {
            return false;
        };

        $this->expectExceptionMessage('disabled');
        $client->getUserAction('sendReminder')->execute();
    }

    public function testDisabled3(): void
    {
        $client = new UaClient($this->pers);
        $client = $client->load(1);

        $client->getUserAction('sendReminder')->enabled = function () {
            return true;
        };

        $client->getUserAction('sendReminder')->execute();
        static::assertTrue(true); // no exception
    }

    public function testFields(): void
    {
        $client = new UaClient($this->pers);
        $client->addUserAction('change_details', ['callback' => 'save', 'fields' => ['name']]);

        $client = $client->load(1);

        static::assertNotSame('Peter', $client->get('name'));
        $client->set('name', 'Peter');
        $client->getUserAction('change_details')->execute();
        static::assertSame('Peter', $client->get('name'));
    }

    public function testFieldsTooDirty1(): void
    {
        $client = new UaClient($this->pers);
        $client->addUserAction('change_details', ['callback' => 'save', 'fields' => ['name']]);

        $client = $client->load(1);

        static::assertNotSame('Peter', $client->get('name'));
        $client->set('name', 'Peter');
        $client->set('reminder_sent', true);

        $this->expectExceptionMessage('dirty fields');
        $client->getUserAction('change_details')->execute();
    }

    public function testFieldsIncorrect(): void
    {
        $client = new UaClient($this->pers);
        $client->addUserAction('change_details', ['callback' => 'save', 'fields' => 'whops_forgot_brackets']);

        $client = $client->load(1);

        static::assertNotSame('Peter', $client->get('name'));
        $client->set('name', 'Peter');

        $this->expectExceptionMessage('must be either array or boolean');
        $client->getUserAction('change_details')->execute();
    }

    public function testConfirmation(): void
    {
        $client = new UaClient($this->pers);
        $client->addUserAction('test');
        $client = $client->load(1);

        $action = $client->getUserAction('test');
        static::assertFalse($action->getConfirmation());

        $action->confirmation = true;
        static::assertSame('Are you sure you wish to execute Test using John?', $action->getConfirmation());

        $action->confirmation = 'Are you sure?';
        static::assertSame('Are you sure?', $action->getConfirmation());

        $action->confirmation = function ($action) {
            return 'Proceed with Test: ' . $action->getEntity()->getTitle();
        };
        static::assertSame('Proceed with Test: John', $action->getConfirmation());
    }
}
