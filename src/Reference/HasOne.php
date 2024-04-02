<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Reference;

class HasOne extends Reference
{
    use Model\FieldPropertiesTrait;
    use Model\JoinLinkTrait;

    #[\Override]
    protected function init(): void
    {
        parent::init();

        if ($this->ourField === null) {
            $this->ourField = $this->link;
        }

        $checkTheirTypeOrig = $this->checkTheirType;
        $this->checkTheirType = false;
        try {
            $analysingTheirModel = $this->createAnalysingTheirModel();
        } finally {
            $this->checkTheirType = $checkTheirTypeOrig;
        }

        // infer our field type from their field
        if (($this->type ?? null) === null) {
            $this->type = $analysingTheirModel->getField($this->getTheirFieldName($analysingTheirModel))->type;
        }

        $this->referenceLink = $this->link; // named differently than in Model\FieldPropertiesTrait

        $fieldPropsRefl = (new \ReflectionClass(Model\FieldPropertiesTrait::class))->getProperties();
        $fieldPropsRefl[] = (new \ReflectionClass(Model\JoinLinkTrait::class))->getProperty('joinName');

        $ourModel = $this->getOurModel();
        if (!$ourModel->hasField($this->ourField)) {
            $fieldSeed = [];
            foreach ($fieldPropsRefl as $fieldPropRefl) {
                $v = $this->{$fieldPropRefl->getName()};
                $vDefault = \PHP_MAJOR_VERSION === 7
                    ? ($fieldPropRefl->getDeclaringClass()->getDefaultProperties()[$fieldPropRefl->getName()] ?? null)
                    : (null ?? $fieldPropRefl->getDefaultValue()); // @phpstan-ignore-line for PHP 7.x
                if ($v !== $vDefault) {
                    $fieldSeed[$fieldPropRefl->getName()] = $v;
                }
            }

            $ourModel->addField($this->ourField, $fieldSeed);
        }

        // TODO seeding thru Model\FieldPropertiesTrait is a hack, at least unset these properties for now
        foreach ($fieldPropsRefl as $fieldPropRefl) {
            if ($fieldPropRefl->getName() !== 'caption') { // "caption" is also defined in Reference class
                unset($this->{$fieldPropRefl->getName()});
            }
        }
    }

    /**
     * Returns our field or id field.
     */
    protected function referenceOurValue(): Field
    {
        // TODO horrible hack to render the field with a table prefix,
        // find a solution how to wrap the field inside custom Field (without owner?)
        $ourModelCloned = clone $this->getOurModel();
        $ourModelCloned->persistenceData['use_table_prefixes'] = true;

        return $ourModelCloned->getReference($this->link)->getOurField();
    }

    /**
     * If our model is loaded, then return their model with respective loaded entity.
     *
     * If our model is not loaded, then return their model with condition set.
     * This can happen in case of deep traversal $model->ref('Many')->ref('one_id'), for example.
     */
    #[\Override]
    public function ref(Model $ourModelOrEntity, array $defaults = []): Model
    {
        $this->assertOurModelOrEntity($ourModelOrEntity);

        $theirModel = $this->createTheirModel($defaults);

        if ($ourModelOrEntity->isEntity()) {
            $this->onHookToTheirModel($theirModel, Model::HOOK_AFTER_SAVE, function (Model $theirEntity) use ($ourModelOrEntity) {
                $theirValue = $this->theirField !== null
                    ? $theirEntity->get($this->theirField)
                    : $theirEntity->getId();

                if (!$this->getOurField()->compare($this->getOurFieldValue($ourModelOrEntity), $theirValue)) {
                    $ourModelOrEntity->set($this->getOurFieldName(), $theirValue)->save();
                }

                $theirEntity->reload();
            });
            $theirModel->reloadAfterSave = false;

            $this->onHookToTheirModel($theirModel, Model::HOOK_AFTER_DELETE, function (Model $theirEntity) use ($ourModelOrEntity) {
                $ourModelOrEntity->setNull($this->getOurFieldName());
            });

            $ourValue = $this->getOurFieldValue($ourModelOrEntity);

            if ($this->getOurFieldName() === $ourModelOrEntity->idField) {
                $this->assertReferenceValueNotNull($ourValue);
                $tryLoad = true;
            } else {
                $tryLoad = false;
            }

            $theirModelOrig = $theirModel;
            if ($ourValue === null) {
                $theirModel = null;
            } else {
                $theirModel->addCondition($this->getTheirFieldName($theirModel), $ourValue);

                $theirModel = $tryLoad
                    ? $theirModel->tryLoadOne()
                    : $theirModel->loadOne();
            }

            if ($theirModel === null) {
                $theirModel = $theirModelOrig->createEntity();
            }
        }

        return $theirModel;
    }
}
