<?php

namespace atk4\data\tests;

use atk4\data\Model;

class STAccount extends Model
{
    public $table = 'account';

    public function init()
    {
        parent::init();

        $this->addField('name');

        $this->hasMany('Transactions', new STGenericTransaction())
            ->addField('balance', ['aggregate'=>'sum', 'field'=>'amount']);

        $this->hasMany('Transactions:Deposit', new STTransaction_Deposit());
        $this->hasMany('Transactions:Withdrawal', new STTransaction_Withdrawal());
        $this->hasMany('Transactions:OB', new STTransaction_OB())
            ->addField('opening_balance', ['aggregate'=>'sum', 'field'=>'amount']);

        $this->hasMany('Transactions:TransferOut', new STTransaction_TransferOut());
        $this->hasMany('Transactions:TransferIn', new STTransaction_TransferIn());
    }

    public static function open($persistence, $name, $amount = 0)
    {
        $m = new self($persistence);
        $m->save($name);

        if ($amount) {
            $m->ref('Transactions:OB')->save(['amount'=>$amount]);
        }

        return $m;
    }

    public function deposit($amount)
    {
        return $this->ref('Transactions:Deposit')->save(['amount'=>$amount]);
    }

    public function withdraw($amount)
    {
        return $this->ref('Transactions:Withdrawal')->save(['amount'=>$amount]);
    }

    public function transferTo(self $account, $amount)
    {
        $out = $this->ref('Transactions:TransferOut')->save(['amount'=>$amount]);
        $in = $account->ref('Transactions:TransferIn')->save(['amount'=>$amount, 'link_id'=>$out->id]);
        $out['link_id'] = $in->id;
        $out->save();
    }
}

class STGenericTransaction extends Model
{
    public $table = 'transaction';
    public $type = null;

    public function init()
    {
        parent::init();

        $this->hasOne('account_id', new STAccount());
        $this->addField('type', ['enum'=>['OB', 'Deposit', 'Withdrawal', 'TransferOut', 'TransferIn']]);

        if ($this->type) {
            $this->addCondition('type', $this->type);
        }
        $this->addField('amount');

        $this->addHook('afterLoad', function (self $m) {
            if (get_class($this) != $m->getClassName()) {
                $cl = '\\'.$this->getClassName();
                $cl = new $cl($this->persistence);
                $cl->load($m->id);

                $this->breakHook($cl);
            }
        });
    }

    public function getClassName()
    {
        return 'atk4\data\tests\STTransaction_'.$this['type'];
    }
}

class STTransaction_OB extends STGenericTransaction
{
    public $type = 'OB';
}

class STTransaction_Deposit extends STGenericTransaction
{
    public $type = 'Deposit';
}

class STTransaction_Withdrawal extends STGenericTransaction
{
    public $type = 'Withdrawal';
}

class STTransaction_TransferOut extends STGenericTransaction
{
    public $type = 'TransferOut';

    public function init()
    {
        parent::init();
        $this->hasOne('link_id', new STTransaction_TransferIn());

        //$this->join('transaction','linked_transaction');
    }
}

class STTransaction_TransferIn extends STGenericTransaction
{
    public $type = 'TransferIn';

    public function init()
    {
        parent::init();
        $this->hasOne('link_id', new STTransaction_TransferOut());
    }
}

/**
 * Implements various tests for deep copying objects.
 */
class SubTypesTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function setUp()
    {
        parent::setUp();

        // populate database for our three models
        $this->getMigration(new STAccount($this->db))->drop()->create();
        $this->getMigration(new STTransaction_TransferOut($this->db))->drop()->create();
    }

    public function testBasic()
    {
        $inheritance = STAccount::open($this->db, 'inheritance', 1000);
        $current = STAccount::open($this->db, 'current');

        $inheritance->transferTo($current, 500);
        $current->withdraw(350);

        $this->assertEquals('atk4\data\tests\STTransaction_OB', get_class($inheritance->ref('Transactions')->load(1)));
        $this->assertEquals('atk4\data\tests\STTransaction_TransferOut', get_class($inheritance->ref('Transactions')->load(2)));
        $this->assertEquals('atk4\data\tests\STTransaction_TransferIn', get_class($current->ref('Transactions')->load(3)));
        $this->assertEquals('atk4\data\tests\STTransaction_Withdrawal', get_class($current->ref('Transactions')->load(4)));

        $cl = [];
        foreach ($current->ref('Transactions') as $tr) {
            $cl[] = get_class($tr);
        }

        $this->assertEquals(['atk4\data\tests\STTransaction_TransferIn', 'atk4\data\tests\STTransaction_Withdrawal'], $cl);
    }
}
