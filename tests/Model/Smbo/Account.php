<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model\Smbo;

use Atk4\Data\Model;

class Account extends Model
{
    public $table = 'account';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');

        $this->hasMany('Payment', ['model' => [Payment::class]])
            ->addField('balance', ['aggregate' => 'sum', 'field' => 'amount', 'type' => 'atk4_money']);
    }

    /**
     * Create and return a transfer model.
     */
    public function transfer(self $a, float $amount): Transfer
    {
        $t = new Transfer($this->getModel()->getPersistence(), ['detached' => true]);
        $t = $t->createEntity();
        $t->set('account_id', $this->getId());
        $t->set('destination_account_id', $a->getId());
        $t->set('amount', -$amount);

        return $t;
    }
}
