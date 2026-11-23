<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\TaskList;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Demo data for the workshop. Every user's password is the part of the email before the "@".
 */
final class AppFixtures extends Fixture
{
    public const string USER1_EMAIL = 'user1@example.com';
    public const string USER2_EMAIL = 'user2@example.com';
    public const string USER3_EMAIL = 'user3@example.com';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user1 = $this->createUser(self::USER1_EMAIL, 'user1');
        $user2 = $this->createUser(self::USER2_EMAIL, 'user2');
        $user3 = $this->createUser(self::USER3_EMAIL, 'user3');
        $manager->persist($user1);
        $manager->persist($user2);
        $manager->persist($user3);

        $user1MainList = new TaskList($user1, 'User 1 main list');
        $user1MainList->addItem('First task for user 1');
        $user1MainList->addItem('Second task for user 1');
        $manager->persist($user1MainList);

        $user1SharedList = new TaskList($user1, 'Shared list of user 1');
        $user1SharedList->addItem('Only task on shared list');
        $user1SharedList->addContributor($user2);
        $manager->persist($user1SharedList);

        $user1ArchivedList = new TaskList($user1, 'Archived list of user 1');
        $user1ArchivedList->addItem('Task in archived list');
        $user1ArchivedList->archive();
        $manager->persist($user1ArchivedList);

        $user2List = new TaskList($user2, 'User 2 main list');
        $user2List->addItem('First task for user 2');
        $user2List->addItem('Second task for user 2');
        $manager->persist($user2List);

        $manager->flush();
    }

    private function createUser(string $email, string $plainPassword): User
    {
        $user = new User($email);
        $user->replaceEncodedPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        return $user;
    }
}
