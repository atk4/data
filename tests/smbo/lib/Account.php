<?php

namespace atk4\data\tests\smbo;

class Account extends \atk4\data\Model
{
    public $table = 'account';

    public function init()
    {
        parent::init();

        $this->addField('name');

        $this->hasMany('Payment', new Payment())
            ->addField('balance', ['aggregate' => 'sum', 'field' => 'amount']);
    }

    /**
     * create and return a trasnfer model.
     */
    public function transfer(self $a, $amount)
    {
        $t = new Transfer($this->persistence, ['detached' => true]);
        $t['account_id'] = $this->id;

        $t['destination_account_id'] = $a->id;

        $t['amount'] = -$amount;

        return $t;
    }
}
