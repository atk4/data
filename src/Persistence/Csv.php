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
     * Name of the file.
     *
     * @var string
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

    public function __construct(string $file, array $defaults = [])
    {
        $this->file = $file;
        $this->setDefaults($defaults);
    }

    protected function initPersistence(Model $model)
    {
        parent::initPersistence($model);

        $this->initFileObject($model);
    }

    public function getRawDataIterator($table): \Iterator
    {
        return new \LimitIterator($this->fileObject, 1);
    }

    public function setRawData(Model $model, $data, $id = null)
    {
        if (!$this->getFileHeader()) {
            $this->initFileHeader($model);
        }

        $emptyRow = array_flip($this->getFileHeader());

        $data = array_intersect_key(array_merge($emptyRow, $data), $emptyRow);

        if ($id === null) {
            while (!$this->fileObject->eof()) {
                $this->fileObject->next();
            }

            $id = $this->fileObject->key();

            $this->lastInsertId = $id;
        } else {
            $this->fileObject->seek($id);
        }

        $this->fileObject->fputcsv($data);

        return $id;
    }

    protected function initFileObject(Model $model)
    {
        if (!file_exists($this->file)) {
            file_put_contents($this->file, '');
        }

        $this->fileObject = new \SplFileObject($this->file, 'r+');
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
        $this->fileObject->seek(0);

        $this->fileObject->fputcsv(array_keys($model->getFields('not system')));
    }

    public function getFileHeader(): array
    {
        $this->fileObject->seek(0);

        return array_map(function ($name) {
            return preg_replace('/[^a-z0-9_-]+/i', '_', $name);
        }, $this->fileObject->current() ?: []);
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

    public function lastInsertId(Model $model = null)
    {
        return $this->lastInsertId;
    }

    public function query(Model $model): AbstractQuery
    {
        return new Csv\Query($model, $this);
    }
}
