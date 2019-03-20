<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Action;
use atk4\data\Persistence_Static;


/**
 * Sample trait designed to extend model
 *
 * @target Model
 */
trait ACReminder
{
    function send_reminder()
    {
        return 'sent reminder to '.$this[$this->title_field];
    }
}

class ACClient extends Model
{
    use ACReminder;

    public function init()
    {
        parent::init();

        $this->addField('name');

        $this->addAction('send_reminder');
    }
}

/**
 * Implements various tests for deep copying objects.
 */
class ActionTest extends \atk4\schema\PHPUnit_SchemaTestCase
{

    public $pers = null;

    public function setUp()
    {
        parent::setUp();

        $this->pers = new Persistence_Static([
            1=>['name'=>'John']
        ]);
    }

    public function testBasic()
    {
        $client = new ACClient($this->pers);

        $client->load(1);
    }
}
