<?php

namespace atk4\data\tests\smbo;
use \atk4\core\Exception;

class Transfer extends Payment {

    protected $detached = false;

    public $other_leg_creation = null;

    function init()
    {
        parent::init();

        $this->j_payment->hasOne('transfer_document_id', new Transfer());

        // only used to create / destroy trasfer legs
        if (!$this->detached) {
            $this->addCondition('transfer_document_id','not',null);
        }

        $this->addField('destination_account_id', ['never_persist'=>true]);

        $this->addHook('beforeSave', function($m) {

            // only for new records and when destination_account_id is set
            if ($m['destination_account_id'] && !$m->id) {

                $this->other_leg_creation = $m2 = new Transfer($this->persistence);

                $m2->set($m->get());
                $m2->unset('destination_account_id');
                $m2['account_id'] = $m['destination_account_id'];
                $m2['amount'] = -$m2['amount']; // neagtive amount
                $m2->reload_after_save = false; // avoid check

                $m['transfer_document_id'] = $m2->save()->id;
            }
        });

        $this->addHook('afterSave', function($m) {
            if($m->other_leg_creation) {
                $m->other_leg_creation->set('transfer_document_id', $m->id)->save();
            }
            $m->other_leg_creation = null;
        });

    }
}
