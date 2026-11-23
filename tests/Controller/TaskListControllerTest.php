<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Tests\Support\ReloadsAppFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('smoke')]
#[Group('stateful')]
final class TaskListControllerTest extends WebTestCase
{
    use ReloadsAppFixturesTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        static::reloadAppFixtures();
    }

    public function testGuestsAreRedirectedToLogin(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testListIsAccessibleForLoggedInUsers(): void
    {
        $this->client->loginUser($this->fixtureUser(AppFixtures::USER1_EMAIL));
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My Todo List');
        self::assertSelectorTextContains('button', 'Create a list');
        self::assertSelectorTextContains('a.active', 'All Lists');
    }

    private function fixtureUser(string $email): User
    {
        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }
}
