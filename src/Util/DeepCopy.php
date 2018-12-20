<?php

namespace atk4\data\Util;

use atk4\core\Exception;
use atk4\data\Model;
use atk4\data\Reference_Many;
use atk4\data\Reference_One;

/**
 * Class DeepCopy implements copying records between two models:.
 *
 * $dc = new DeepCopy();
 *
 * $dc->from($user);
 * $dc->to(new ArchivedUser());
 * $dc->with('AuditLog');
 * $dc->copy();
 */
class DeepCopy
{
    use \atk4\core\DebugTrait;

    /**
     * @var \atk4\data\Model from which we want to copy records.
     */
    protected $source;

    /**
     * @var \atk4\data\Model in which we want to copy records into.
     */
    protected $destination;

    /**
     * @var array containing references which we need to copy. May contain sub-arrays: ['Invoices'=>['Lines']]
     */
    protected $references = [];

    /**
     * @var array contains array similar to references but containing list of excluded fields:
     * e.g. ['Invoices'=>['Lines'=>['vat_rate_id']]]
     */
    protected $exclusions = [];

    /**
     * @var array while copying, will record mapped records in format [$table => ['old_id'=>'new_id']]
     */
    public $mapping = [];

    /**
     * Set model from which to copy records.
     *
     * @param Model $source
     *
     * @return $this
     */
    public function from(Model $source)
    {
        $this->source = $source;

        return $this;
    }

    /**
     * Set model in which to copy records into.
     *
     * @param Model $destination
     *
     * @return $this
     */
    public function to(Model $destination)
    {
        $this->destination = $destination;

        if (!$this->destination->persistence) {
            $this->source->persistence->add($this->destination);
        }

        return $this;
    }

    /**
     * Set references to copy.
     *
     * @param array $references
     *
     * @return $this
     */
    public function with(array $references)
    {
        $this->references = $references;

        return $this;
    }

    public function excluding(array $exclusions)
    {
        $this->exclusions = $exclusions;

        return $this;
    }

    /**
     * Will extract non-numeric keys from the array
     *
     * @param $array
     * @return array
     */
    protected function extractKeys($array): array
    {
        $result = [];
        foreach($array as $key=>$val) {
            if (is_numeric($key)) {
                $result[$val] = [];
            } else {
                $result[$key] = $val;
            }
        }
        return $result;
    }

    /**
     * Copy records.
     *
     * @return Model Destination model
     */
    public function copy()
    {
        return $this->_copy($this->source, $this->destination, $this->references, $this->exclusions)->reload();
    }

    /**
     * Internal method for copying records.
     *
     * @param Model $source
     * @param Model $destination
     * @param array $references
     * @param array $exclusions of fields to exclude
     *
     * @throws DeepCopyException
     * @throws Exception
     *
     * @return Model Destination model
     */
    protected function _copy(Model $source, Model $destination, array $references, array $exclusions)
    {
        try {
            // Perhaps source was already copied, then simply load destination model and return
            if (isset($this->mapping[$source->table]) && isset($this->mapping[$source->table][$source->id])) {
                return $destination->load($this->mapping[$source->table][$source->id]);
            }

            // TODO transform data from source to destination with a possible callback
            // $data = $source->get(); transformData($data);
            $data = $source->get();
            unset($data[$source->id_field]);
            foreach ($this->extractKeys($exclusions) as $key => $val) {
                unset($data[$key]);
            }
            $this->debug("Considering $ref_key");

            // TODO add a way here to look for duplicates based on unique fields
            // foreach($destination->unique fields) { try load by

            // Copy fields as they are
            foreach ($data as $key => $val) {
                if (
                    ($field = $destination->hasField($key)) &&
                    $field->isEditable()
                ) {
                    $destination->set($key, $val);
                }
            }
            $destination->hook('afterCopy', [$source]);

            // Look for hasOne references that needs to be mapped. Make sure records can be mapped, or copy them
            foreach ($references as $ref_key => $ref_val) {
                if (is_numeric($ref_key)) {
                    $ref_key = $ref_val;
                    $ref_val = [];
                }

                if (($ref = $source->hasRef($ref_key)) && $ref instanceof Reference_One) {

                    // load destination model through $source
                    $source_table = $ref->refModel()->table;

                    if (
                        isset($this->mapping[$source_table]) &&
                        array_key_exists($source[$ref_key], $this->mapping[$source_table])
                    ) {
                        // no need to deep copy, simply alter ID
                        $destination[$ref_key] = $this->mapping[$source_table][$source[$ref_key]];
                    } else {
                        // hasOne points to null!
                        if (!$source[$ref_key]) {
                            $destination[$ref_key] = $source[$ref_key];
                            continue;
                        }

                        // pointing to non-existent record. Would need to copy
                        try {
                            $destination[$ref_key] = $this->_copy(
                                $source->ref($ref_key),
                                $destination->refModel($ref_key),
                                $ref_val,
                                $exclusions[$ref_key] ?? []
                            )->id;
                        } catch (DeepCopyException $e) {
                            throw $e->addDepth($ref_key);
                        }
                    }
                }
            }

            // Next copy our own data
            $destination->save();

            // Store mapping
            $this->mapping[$source->table][$source->id] = $destination->id;

            // Next look for hasMany relationships and copy those too

            foreach ($this->extractKeys($references) as $ref_key => $ref_val) {
                if (($ref = $source->hasRef($ref_key)) && $ref instanceof Reference_Many) {

                    // No mapping, will always copy
                    foreach ($source->ref($ref_key) as $ref_model) {
                        $this->_copy(
                            $ref_model,
                            $destination->ref($ref_key),
                            $ref_val,
                            $exclusions[$ref_key] ?? []
                        );
                    }
                }
            }

            return $destination;
        } catch (\atk4\core\Exception $e) {
            throw new DeepCopyException([
                'Problem cloning model',
                'source'=>$source,
                'destination'=>$destination,
                'depth'=>'.'
                ], null, $e);
        }
    }
}
