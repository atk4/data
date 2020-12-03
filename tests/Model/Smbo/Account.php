<?php

declare(strict_types=1);

namespace atk4\data\Tests\Model\Smbo;

class Account extends \atk4\data\Model
{
    public $table = 'account';

    protected function init(): void
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
        $t->set('account_id', $this->getId());

        $t->set('destination_account_id', $a->getId());

        $t->set('amount', -$amount);

        return $t;
    }
}
