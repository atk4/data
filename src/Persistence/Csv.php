<?php

declare(strict_types=1);

namespace atk4\data\Persistence;

use atk4\data\Exception;
use atk4\data\Model;
use atk4\data\Persistence;

/**
 * Implements persistence driver that can save data and load from CSV file.
 * This basic driver only offers the load/save. It does not offer conditions or
 * id-specific operations. You can only use a single persistence object with
 * a single file.
 *
 * $p = new Persistence\Csv('file.csv');
 * $m = new MyModel($p);
 * $data = $m->export();
 *
 * Alternatively you can write into a file. First operation you perform on
 * the persistence will determine the mode.
 *
 * $p = new Persistence\Csv('file.csv');
 * $m = new MyModel($p);
 * $m->import($data);
 */
class Csv extends Persistence
{
    /**
     * Name of the file or file object.
     *
     * @var string|\SplFileObject
     */
    public $file;

    /**
     * Line in CSV file.
     *
     * @var int
     */
    public $line = 0;

    /**
     * Delimiter in CSV file.
     *
     * @var string
     */
    public $delimiter = ',';

    /**
     * Enclosure in CSV file.
     *
     * @var string
     */
    public $enclosure = '"';

    /**
     * Escape character in CSV file.
     *
     * @var string
     */
    public $escape_char = '\\';

    /**
     * File access object.
     *
     * @var \SplFileObject
     */
    protected $fileObject;

    protected $lastInsertId;

    public function __construct($file, array $defaults = [])
    {
        $this->file = $file;
        $this->setDefaults($defaults);
    }

    protected function initPersistence(Model $model)
    {
        parent::initPersistence($model);

        $this->initFileObject($model);
    }

    public function getRawDataIterator(Model $model): \Iterator
    {
        return (function ($iterator) use ($model) {
            foreach ($iterator as $id => $row) {
                $row = array_combine($this->getFileHeader(), $row);

                yield $id - 1 => $this->getRowWithId($model, $row, $id);
            }
        })(new \LimitIterator($this->fileObject, 1));
    }

    public function setRawData(Model $model, $row, $id = null)
    {
        if (!$this->getFileHeader()) {
            $this->initFileHeader($model);
        }

        $emptyRow = array_flip($this->getFileHeader());

        $row = array_intersect_key(array_merge($emptyRow, $this->getRowWithId($model, $row, $id)), $emptyRow);

        $id = $id ?? $this->lastInsertId;

        $this->fileObject->seek($id);

        $this->fileObject->fputcsv($row);

        return $id;
    }

    private function getRowWithId(Model $model, array $row, $id = null)
    {
        if ($id === null) {
            $id = $this->generateNewId($model);
        }

        if ($model->id_field) {
            $idField = $model->getField($model->id_field);
            $idColumnName = $idField->actual ?? $idField->short_name;

            if (array_key_exists($idColumnName, $row)) {
                $this->assertNoIdMismatch($row[$idColumnName], $id);
                unset($row[$idColumnName]);
            }

            // typecastSave value so we can use strict comparison
            $row = [$idColumnName => $this->typecastSaveField($idField, $id)] + $row;
        }

        return $row;
    }

    private function assertNoIdMismatch($idFromRow, $id): void
    {
        if ($idFromRow !== null && (is_int($idFromRow) ? (string) $idFromRow : $idFromRow) !== (is_int($id) ? (string) $id : $id)) {
            throw (new Exception('Row constains ID column, but it does not match the row ID'))
                ->addMoreInfo('idFromKey', $id)
                ->addMoreInfo('idFromData', $idFromRow);
        }
    }

    protected function initFileObject(Model $model)
    {
        if (is_string($this->file)) {
            if (!file_exists($this->file)) {
                file_put_contents($this->file, '');
            }

            $this->fileObject = new \SplFileObject($this->file, 'r+');
        } elseif ($this->file instanceof \SplFileObject) {
            $this->fileObject = $this->file;
        }

        $this->fileObject->setFlags(
            \SplFileObject::READ_CSV |
            \SplFileObject::SKIP_EMPTY |
            \SplFileObject::DROP_NEW_LINE |
            \SplFileObject::READ_AHEAD
        );

        $this->fileObject->setCsvControl($this->delimiter, $this->enclosure, $this->escape_char);
    }

    protected function initFileHeader(Model $model): void
    {
        $this->executeRestoringPointer(function () use ($model) {
            $this->fileObject->seek(0);

            $this->fileObject->fputcsv(array_keys($model->getFields('not system')));
        });
    }

    public function getFileHeader(): array
    {
        $header = $this->executeRestoringPointer(function () {
            $this->fileObject->seek(0);

            return $this->fileObject->current();
        });

        return array_map(function ($name) {
            return preg_replace('/[^a-z0-9_-]+/i', '_', $name);
        }, $header ?: []);
    }

    private function executeRestoringPointer(\Closure $fx, array $args = [])
    {
        $position = $this->fileObject->key();

        $result = $fx(...$args);

        $this->fileObject->seek($position);

        return $result;
    }

    /**
     * Deletes record in data array.
     *
     * @param mixed $id
     */
    public function delete(Model $model, $id, string $table = null)
    {
        throw new Exception('Deleting records is not supported in CSV persistence.');
    }

    public function lastInsertId(Model $model): string
    {
        return $this->lastInsertId;
    }

    public function query(Model $model): AbstractQuery
    {
        return new Csv\Query($model, $this);
    }

    public function generateNewId(Model $model)
    {
        while (!$this->fileObject->eof()) {
            $this->fileObject->next();
        }

        $this->lastInsertId = $this->fileObject->key();

        return $this->lastInsertId;
    }
}
