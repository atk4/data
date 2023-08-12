<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model\Smbo;

class Transfer extends Payment
{
    /** @var bool */
    public $detached = false;
    /** @var static|null */
    public $otherLegCreation;

    protected function init(): void
    {
        parent::init();

        $this->jPayment->hasOne('transfer_document_id', ['model' => [self::class]]);

        // only used to create / destroy transfer legs
        if (!$this->detached) {
            $this->addCondition('transfer_document_id', '!=', null);
        }

        $this->addField('destination_account_id', ['type' => 'integer', 'neverPersist' => true]);

        $this->onHookShort(self::HOOK_BEFORE_SAVE, function () {
            // only for new records and when destination_account_id is set
            if ($this->get('destination_account_id') && !$this->getId()) {
                // In this section we test if "clone" works ok

                $this->otherLegCreation = $m2 = clone $this;
                $m2->set('account_id', $m2->get('destination_account_id'));
                $m2->set('amount', -$m2->get('amount'));

                $m2->_unset('destination_account_id');

                $this->set('transfer_document_id', $m2->save()->getId());
            }
        });

        $this->onHookShort(self::HOOK_AFTER_SAVE, function () {
            if ($this->otherLegCreation) {
                $this->otherLegCreation->set('transfer_document_id', $this->getId())->save();
            }
            $this->otherLegCreation = null;
        });
    }
}
