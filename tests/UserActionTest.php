<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Schema\TestCase;

trait UaReminderTrait
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
    use UaReminderTrait;

    public $caption = 'UaClient';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('reminder_sent', ['type' => 'boolean']);

        // this action can be invoked from UI
        $this->addUserAction('sendReminder');

        // this action will be system action, so it will not be invocable from UI
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
            ['name' => 'Peter'],
        ]);
    }

    public function testBasic(): void
    {
        $client = new UaClient($this->pers);

        self::assertCount(4, $client->getUserActions()); // don't return system actions here, but include add/edit/delete
        self::assertCount(0, $client->getUserActions(Model\UserAction::APPLIES_TO_ALL_RECORDS)); // don't return system actions here

        // action takes no arguments. If it would, we should be able to find info about those
        $act1 = $client->getUserActions()['sendReminder'];
        self::assertSame([], $act1->args);
        self::assertSame(Model\UserAction::APPLIES_TO_SINGLE_RECORD, $act1->appliesTo);

        // load record, before executing, because scope is single record
        $client = $client->load(1);

        $act1 = $client->getModel()->getUserActions()['sendReminder'];
        $act1 = $act1->getActionForEntity($client);
        self::assertNotTrue($client->get('reminder_sent'));
        $res = $act1->execute();
        self::assertTrue($client->get('reminder_sent'));

        self::assertSame('sent reminder to John', $res);

        // test system action
        $act2 = $client->getModel()->getUserAction('backupClients');

        // action takes no arguments. If it would, we should be able to find info about those
        self::assertSame([], $act2->args);
        self::assertSame(Model\UserAction::APPLIES_TO_ALL_RECORDS, $act2->appliesTo);

        $res = $act2->execute();
        self::assertSame('backs up all clients', $res);

        // non-existing action
        self::assertFalse($client->hasUserAction('foo'));
    }

    public function testCustomSeedClass(): void
    {
        $customClass = get_class(new class() extends Model\UserAction {});

        $client = new UaClient($this->pers);
        $client->addUserAction('foo', [$customClass]);

        self::assertSame($customClass, get_class($client->getUserAction('foo')));
    }

    public function testExecuteUndefinedMethodException(): void
    {
        $client = new UaClient($this->pers);
        $client->addUserAction('new_client');
        $client = $client->load(1);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to undefined method');
        $client->executeUserAction('new_client');
    }

    public function testPreview(): void
    {
        $client = new UaClient($this->pers);
        $client->addUserAction('say_name', static function (UaClient $m) {
            return $m->get('name');
        });

        $client = $client->load(1);

        self::assertSame('John', $client->getUserAction('say_name')->execute());

        $client->getUserAction('say_name')->preview = static function (UaClient $m) {
            return 'will say ' . $m->get('name');
        };
        self::assertSame('will say John', $client->getUserAction('say_name')->preview());

        $client->getModel()->addUserAction('also_backup', ['callback' => 'backupClients']);
        self::assertSame('backs up all clients', $client->getUserAction('also_backup')->execute());

        $client->getUserAction('also_backup')->preview = 'backupClients';
        self::assertSame('backs up all clients', $client->getUserAction('also_backup')->preview());

        self::assertSame('Also Backup UaClient', $client->getUserAction('also_backup')->getDescription());
    }

    public function testAppliesToSingleRecordNotEntityException(): void
    {
        $client = new UaClient($this->pers);

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Expected entity, but instance is a model');
        $client->executeUserAction('sendReminder');
    }

    public function testAppliesToAllRecordsEntityException(): void
    {
        $client = new UaClient($this->pers);
        $client = $client->load(1);

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Expected model, but instance is an entity');
        $client->executeUserAction('backupClients');
    }

    public function testAppliesToSingleRecordNotLoadedException(): void
    {
        $client = new UaClient($this->pers);
        $client = $client->createEntity();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User action can be executed on loaded entity only');
        $client->executeUserAction('sendReminder');
    }

    public function testAppliesToNoRecordsLoadedRecordException(): void
    {
        $client = new UaClient($this->pers);
        $client->addUserAction('new_client', ['appliesTo' => Model\UserAction::APPLIES_TO_NO_RECORDS]);
        $client = $client->load(1);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User action can be executed on new entity only');
        $client->executeUserAction('new_client');
    }

    public function testNotDefinedException(): void
    {
        $client = new UaClient($this->pers);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User action is not defined');
        $client->getUserAction('non_existent_action');
    }

    public function testDisabledBoolException(): void
    {
        $client = new UaClient($this->pers);
        $client = $client->load(1);

        $client->getUserAction('sendReminder')->enabled = false;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User action is disabled');
        $client->getUserAction('sendReminder')->execute();
    }

    public function testDisabledClosureException(): void
    {
        $client = new UaClient($this->pers);
        $client = $client->load(1);

        $client->getUserAction('sendReminder')->enabled = static function (UaClient $m) {
            return true;
        };
        $client->getUserAction('sendReminder')->execute();

        $client->getUserAction('sendReminder')->enabled = static function (UaClient $m) {
            return false;
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User action is disabled');
        $client->getUserAction('sendReminder')->execute();
    }

    public function testFields(): void
    {
        $client = new UaClient($this->pers);
        $client->addUserAction('change_details', ['callback' => 'save', 'fields' => ['name']]);

        $client = $client->load(1);

        self::assertNotSame('Peter', $client->get('name'));
        $client->set('name', 'Peter');
        $client->getUserAction('change_details')->execute();
        self::assertSame('Peter', $client->get('name'));
    }

    public function testFieldsTooDirtyException(): void
    {
        $client = new UaClient($this->pers);
        $client->addUserAction('change_details', ['callback' => 'save', 'fields' => ['name']]);

        $client = $client->load(1);

        self::assertNotSame('Peter', $client->get('name'));
        $client->set('name', 'Peter');
        $client->set('reminder_sent', true);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User action cannot be executed when unrelated fields are dirty');
        $client->getUserAction('change_details')->execute();
    }

    public function testConfirmation(): void
    {
        $client = new UaClient($this->pers);
        $client->addUserAction('test');
        $client = $client->load(1);

        $action = $client->getUserAction('test');
        self::assertFalse($action->getConfirmation());

        $action->confirmation = true;
        self::assertSame('Are you sure you wish to execute Test using John?', $action->getConfirmation());

        $action->confirmation = 'Are you sure?';
        self::assertSame('Are you sure?', $action->getConfirmation());

        $action->confirmation = static function (Model\UserAction $action) {
            return 'Proceed with Test: ' . $action->getEntity()->getTitle();
        };
        self::assertSame('Proceed with Test: John', $action->getConfirmation());
    }
}
