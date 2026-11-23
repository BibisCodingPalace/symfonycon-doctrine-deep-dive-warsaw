<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Tests\Support\ReloadsAppFixturesTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base class for repository tests which run against the data of {@see \App\DataFixtures\AppFixtures}.
 */
abstract class AbstractRepositoryKernelTestCase extends KernelTestCase
{
    use ReloadsAppFixturesTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        static::reloadAppFixtures();
    }
}
