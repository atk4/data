<?php

namespace atk4\data\tests\smbo;

class Payment extends Document
{
    public function init()
    {
        parent::init();

        $this->addCondition('doc_type', 'payment');

        $j_p = $this->j_payment = $this->join('payment.document_id');

        $j_p->addField('cheque_no');
        $j_p->hasOne('account_id', new Account());

        $j_p->addField('misc_payment', ['type' => 'bool']);
    }
}
