<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\DataFixtures\AppFixtures;
use App\Entity\TaskList;
use App\Entity\User;
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

    protected static function fixtureUser(string $email): User
    {
        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user, sprintf('Expected user "%s" to exist after loading the fixtures.', $email));

        return $user;
    }

    protected static function fixtureList(string $title): TaskList
    {
        $taskList = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(TaskList::class)
            ->findOneBy(['title' => $title]);
        self::assertInstanceOf(TaskList::class, $taskList, sprintf('Expected list "%s" to exist after loading the fixtures.', $title));

        return $taskList;
    }
}
