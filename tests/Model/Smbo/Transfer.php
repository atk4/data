<?php

declare(strict_types=1);

namespace atk4\data\tests\Model\Smbo;

class Transfer extends Payment
{
    public $detached = false;

    public $other_leg_creation;

    protected function init(): void
    {
        parent::init();

        $this->j_payment->hasOne('transfer_document_id', new self());

        // only used to create / destroy trasfer legs
        if (!$this->detached) {
            $this->addCondition('transfer_document_id', 'not', null);
        }

        $this->addField('destination_account_id', ['never_persist' => true]);

        $this->onHook(self::HOOK_BEFORE_SAVE, function ($m) {
            // only for new records and when destination_account_id is set
            if ($m->get('destination_account_id') && !$m->id) {
                // In this section we test if "clone" works ok

                $this->other_leg_creation = $m2 = clone $m;
                $m2->set('account_id', $m2->get('destination_account_id'));
                $m2->set('amount', -$m2->get('amount'));

                $m2->_unset('destination_account_id');

                /*/

                // If clone is not working, then this is a current work-around

                $this->other_leg_creation = $m2 = new Transfer($this->persistence);
                $m2->set($m->get());
                $m2->unset('destination_account_id');
                $m2->set('account_id', $m->get('destination_account_id'));
                $m2->set('amount', -$m2->get('amount')); // neagtive amount

                // */

                $m2->reload_after_save = false; // avoid check

                $m->set('transfer_document_id', $m2->save()->id);
            }
        });

        $this->onHook(self::HOOK_AFTER_SAVE, function ($m) {
            if ($m->other_leg_creation) {
                $m->other_leg_creation->set('transfer_document_id', $m->id)->save();
            }
            $m->other_leg_creation = null;
        });
    }
}
