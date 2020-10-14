<?php

declare(strict_types=1);

namespace atk4\schema;

/**
 * Makes sure your database is adjusted for one or several models,
 * that you specify.
 */
class MigratorConsole extends \atk4\ui\Console
{
    /** @var string Name of migrator class to use */
    public $migrator_class;

    /**
     * Provided with array of models, perform migration for each of them.
     *
     * @param array $models
     */
    public function migrateModels($models)
    {
        // run inside callback
        $this->set(function ($console) use ($models) {
            $console->notice('Preparing to migrate models');

            $persistence = $console->getApp()->db;

            foreach ($models as $model) {
                if (!is_object($model)) {
                    $model = $this->factory((array) $model);
                    $persistence->add($model);
                }

                $migrator = $this->migrator_class ?: Migration::class;

                $result = $migrator::of($model)->run();

                $console->debug('  ' . get_class($model) . '.. ' . $result);
            }

            $console->notice('Done with migration');
        });
    }
}
