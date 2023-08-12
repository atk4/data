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

    /**
     * Reference\HasOne will also add a field corresponding
     * to 'ourField' unless it exists.
     */
    protected function init(): void
    {
        parent::init();

        if (!$this->ourField) {
            $this->ourField = $this->link;
        }

        // for references use "integer" as a default type
        if (!(new \ReflectionProperty($this, 'type'))->isInitialized($this)) {
            $this->type = 'integer';
        }

        $this->referenceLink = $this->link; // named differently than in Model\FieldPropertiesTrait

        $fieldPropsRefl = (new \ReflectionClass(Model\FieldPropertiesTrait::class))->getProperties();
        $fieldPropsRefl[] = (new \ReflectionClass(Model\JoinLinkTrait::class))->getProperty('joinName');

        $ourModel = $this->getOurModel(null);
        if (!$ourModel->hasField($this->ourField)) {
            $fieldSeed = [];
            foreach ($fieldPropsRefl as $fieldPropRefl) {
                $v = $this->{$fieldPropRefl->getName()};
                $vDefault = \PHP_MAJOR_VERSION < 8
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
        $ourModelCloned = clone $this->getOurModel(null);
        $ourModelCloned->persistenceData['use_table_prefixes'] = true;

        return $ourModelCloned->getReference($this->link)->getOurField();
    }

    /**
     * If our model is loaded, then return their model with respective record loaded.
     *
     * If our model is not loaded, then return their model with condition set.
     * This can happen in case of deep traversal $model->ref('Many')->ref('one_id'), for example.
     */
    public function ref(Model $ourModel, array $defaults = []): Model
    {
        $ourModel = $this->getOurModel($ourModel);
        $theirModel = $this->createTheirModel($defaults);

        if ($ourModel->isEntity()) {
            $ourValue = $this->getOurFieldValue($ourModel);

            if ($this->getOurFieldName() === $ourModel->idField) {
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

        // their model will be reloaded after saving our model to reflect changes in referenced fields
        $theirModel->getModel(true)->reloadAfterSave = false;

        if ($ourModel->isEntity()) {
            $this->onHookToTheirModel($theirModel, Model::HOOK_AFTER_SAVE, function (Model $theirModel) use ($ourModel) {
                $theirValue = $this->theirField ? $theirModel->get($this->theirField) : $theirModel->getId();

                if (!$this->getOurField()->compare($this->getOurFieldValue($ourModel), $theirValue)) {
                    $ourModel->set($this->getOurFieldName(), $theirValue)->save();
                }

                $theirModel->reload();
            });

            // add hook to set our field = null when record of referenced model is deleted
            $this->onHookToTheirModel($theirModel, Model::HOOK_AFTER_DELETE, function (Model $theirModel) use ($ourModel) {
                $ourModel->setNull($this->getOurFieldName());
            });
        }

        return $theirModel;
    }
}
