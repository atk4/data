<?php

declare(strict_types=1);

namespace atk4\data\Tests\Model\Smbo;

class Document extends \atk4\data\Model
{
    public $table = 'document';

    protected function init(): void
    {
        parent::init();

        // Documest is sent from one Contact to Another
        $this->hasOne('contact_from_id', new Contact());
        $this->hasOne('contact_to_id', new Contact());

        $this->addField('doc_type', ['enum' => ['invoice', 'payment']]);

        $this->addField('amount', ['type' => 'money']);
    }
}
