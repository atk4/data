<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model\Smbo;

use Atk4\Data\Model;

class Payment extends Document
{
    /** @var Model\Join */
    public $jPayment;

    protected function init(): void
    {
        parent::init();

        $this->addCondition('doc_type', 'payment');

        $this->jPayment = $this->join('payment.document_id', ['allowDangerousForeignTableUpdate' => true]);

        $this->jPayment->addField('cheque_no');
        $this->jPayment->hasOne('account_id', ['model' => [Account::class]]);
        $this->jPayment->addField('misc_payment', ['type' => 'boolean']);
    }
}
