<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\DataFixtures\AppFixtures;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Purges the test database and loads {@see AppFixtures}, so that every test starts from the same data.
 *
 * Requires a booted kernel ({@see KernelTestCase::bootKernel()} or WebTestCase::createClient()).
 *
 * @mixin KernelTestCase
 */
trait ReloadsAppFixturesTrait
{
    protected static function reloadAppFixtures(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        (new ORMPurger($entityManager))->purge();

        (new AppFixtures($container->get(UserPasswordHasherInterface::class)))->load($entityManager);
        $entityManager->clear();
    }
}
