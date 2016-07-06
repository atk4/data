<?php
namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class LimitOrderTest extends SQLTestCase
{

    public function testBasic()
    {
        $a = [
            'invoice'=>[
                ['total_net'=>10],
                ['total_net'=>20],
                ['total_net'=>15],
            ]];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $i = (new Model($db, 'invoice'))->addFields(['total_net','total_vat']);
        $i->addExpression('total_gross', '[total_net]+[total_vat]');

        $i->setOrder('total_net');
        $i->onlyFields(['total_gross']);
        $data = $i->export();

        var_dump($data);
    }
}
