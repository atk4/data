<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Model;
use Atk4\Data\Model\Phpstan\IsEntity;
use Atk4\Data\Model\Phpstan\IsLoaded;
use Atk4\Data\Tests\Model\Female;
use Atk4\Data\Tests\Model\Male;
use Mvorisek\Atk4\Hintable\Phpstan\AssertSamePhpstanTypeTrait;
use Mvorisek\Atk4\Hintable\Phpstan\PhpstanUtil;

class ModelPhpstanTest extends TestCase
{
    use AssertSamePhpstanTypeTrait;

    public function testVirtualInterfacesSimple(): void
    {
        $model = new Model();
        $this->assertSamePhpstanType(Model::class, $model);
        $this->assertSamePhpstanType(Model::class, get_class($model)::assertInstanceOf($model));

        $entity = $model->createEntity();
        $this->assertSamePhpstanType(Model::class . '&' . IsEntity::class, $entity);
        $this->assertSamePhpstanType(Model::class, $entity->getModel());

        if (PhpstanUtil::alwaysFalseAnalyseOnly()) {
            $model->assertIsEntity();
            $this->assertSamePhpstanType(Model::class . '&' . IsEntity::class, $model);
            $model->assertIsModel();
            $this->assertSamePhpstanType(Model::class, $model);

            $entity = $model->load(1);
            $this->assertSamePhpstanType(Model::class . '&' . IsLoaded::class, $entity);
            $entity->unload();
            $this->assertSamePhpstanType(Model::class . '&' . IsEntity::class, $entity);
            $entity->assertIsLoaded();
            $this->assertSamePhpstanType(Model::class . '&' . IsLoaded::class, $entity);
            $entity->assertIsEntity();
            $this->assertSamePhpstanType(Model::class . '&' . IsLoaded::class, $entity);
        }

        // model and entity is mutually exclusive
        $this->assertSamePhpstanType(Model::class, random_int(0, 1) === 0 ? $model : $entity);
    }

    public function testVirtualInterfacesUnion(): void
    {
        $modelClass = random_int(0, 1) === 0 ? Female::class : Male::class;
        $model = new $modelClass();
        $this->assertSamePhpstanType(Female::class . '|' . Male::class, $model);
        $this->assertSamePhpstanType(Female::class . '|' . Male::class, get_class($model)::assertInstanceOf($model));

        $entity = $model->createEntity();
        $this->assertSamePhpstanType('(' . IsEntity::class . '&' . Female::class . ')|(' . IsEntity::class . '&' . Male::class . ')', $entity);
        $this->assertSamePhpstanType(Female::class . '|' . Male::class, $entity->getModel());

        if (PhpstanUtil::alwaysFalseAnalyseOnly()) {
            $model->assertIsEntity();
            $this->assertSamePhpstanType('(' . IsEntity::class . '&' . Female::class . ')|(' . IsEntity::class . '&' . Male::class . ')', $model);
            $model->assertIsModel();
            $this->assertSamePhpstanType(Female::class . '|' . Male::class, $model);

            $entity = $model->load(1);
            $this->assertSamePhpstanType('(' . IsLoaded::class . '&' . Female::class . ')|(' . IsLoaded::class . '&' . Male::class . ')', $entity);
            $entity->unload();
            $this->assertSamePhpstanType('(' . IsEntity::class . '&' . Female::class . ')|(' . IsEntity::class . '&' . Male::class . ')', $entity);
            $entity->assertIsLoaded();
            $this->assertSamePhpstanType('(' . IsLoaded::class . '&' . Female::class . ')|(' . IsLoaded::class . '&' . Male::class . ')', $entity);
            $entity->assertIsEntity();
            $this->assertSamePhpstanType('(' . IsLoaded::class . '&' . Female::class . ')|(' . IsLoaded::class . '&' . Male::class . ')', $entity);
        }
    }
}
