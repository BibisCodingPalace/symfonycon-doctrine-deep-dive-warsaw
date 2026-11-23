<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Tests\Support\LoadsAppFixturesTrait;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('integration')]
#[Group('stateful')]
final class TaskListControllerTest extends WebTestCase
{
    use LoadsAppFixturesTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        static::loadAppFixtures();
    }

    public function testGuestsAreRedirectedToLogin(): void
    {
        $this->client->request('GET', '/show/'.self::fixtureList('User 1 main list')->getId());

        self::assertResponseRedirects('/login');
    }

    public function testOverviewShowsSummarizedListsOfTheUser(): void
    {
        $this->loginAs(AppFixtures::USER1_EMAIL);
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My Todo List');
        self::assertSelectorTextContains('a.active', 'All Lists');
        self::assertSelectorCount(3, '.card');
        self::assertAnySelectorTextContains('.card', 'User 1 Main List');
        self::assertAnySelectorTextContains('.card', 'list with 2 tasks');
        self::assertSelectorTextNotContains('main', 'User 2 Main List');
    }

    #[TestWith(['own', 'My Lists', 3, 'User 1 Main List'])]
    #[TestWith(['active', 'Active Lists', 2, 'Shared List Of User 1'])]
    #[TestWith(['archived', 'Archived Lists', 1, 'Archived List Of User 1'])]
    #[TestWith(['contributing', 'Contributing Lists', 0, 'No lists found.'])]
    public function testFilterShowsMatchingLists(string $filter, string $activeTab, int $expectedCards, string $expectedText): void
    {
        $this->loginAs(AppFixtures::USER1_EMAIL);
        $this->client->request('GET', '/?filter='.$filter);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('a.active', $activeTab);
        self::assertSelectorCount($expectedCards, '.card');
        self::assertSelectorTextContains('main', $expectedText);
    }

    public function testContributingFilterShowsSharedList(): void
    {
        $this->loginAs(AppFixtures::USER2_EMAIL);
        $this->client->request('GET', '/?filter=contributing');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.card');
        self::assertSelectorTextContains('.card', 'Shared List Of User 1');
        self::assertSelectorTextContains('.card', 'list with 1 tasks');
    }

    public function testUnknownFilterIsNotFound(): void
    {
        $this->loginAs(AppFixtures::USER1_EMAIL);
        $this->client->request('GET', '/?filter=unknown');

        self::assertResponseStatusCodeSame(404);
    }

    public function testShowRendersListFromDatabase(): void
    {
        $this->loginAs(AppFixtures::USER1_EMAIL);
        $this->client->request('GET', '/show/'.self::fixtureList('User 1 main list')->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'User 1 Main List');
        self::assertSelectorCount(2, '.list-group-item');
    }

    public function testShowIsForbiddenForUsersWhoAreNotMembers(): void
    {
        $this->loginAs(AppFixtures::USER3_EMAIL);
        $this->client->request('GET', '/show/'.self::fixtureList('User 1 main list')->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testRecentPageShowsTodaysTasksForList(): void
    {
        $this->loginAs(AppFixtures::USER1_EMAIL);
        $this->client->request('GET', '/recent/'.self::fixtureList('User 1 main list')->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, '.list-group-item');
        self::assertSelectorTextContains('.list-group', 'First Task For User 1');
    }

    public function testCreateListRedirectsToTheNewList(): void
    {
        $this->loginAs(AppFixtures::USER3_EMAIL);
        $this->client->request('GET', '/');
        $this->client->submitForm('Create a list', ['name' => 'Holiday packing']);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('h2', 'Holiday Packing');

        $this->client->request('GET', '/');
        self::assertSelectorCount(1, '.card');
    }

    public function testCreateListWithEmptyNameShowsError(): void
    {
        $this->loginAs(AppFixtures::USER3_EMAIL);
        $this->client->request('POST', '/', ['name' => ' ']);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'List title cannot be empty.');
    }

    public function testAddTaskAndCloseIt(): void
    {
        $this->loginAs(AppFixtures::USER1_EMAIL);
        $this->client->request('GET', '/show/'.self::fixtureList('Shared list of user 1')->getId());

        $this->client->submitForm('Add task', ['summary' => 'Buy milk']);
        $this->client->followRedirect();
        self::assertSelectorCount(2, '.list-group-item');
        self::assertSelectorTextContains('.list-group', 'Buy Milk');
        self::assertSelectorNotExists('.list-group-item.bg-light');

        $this->client->submitForm('close');
        $this->client->followRedirect();
        self::assertSelectorCount(1, '.list-group-item.bg-light');
        self::assertSelectorTextContains('.list-group-item.bg-light', 'reopen');
    }

    public function testArchiveList(): void
    {
        $this->loginAs(AppFixtures::USER1_EMAIL);
        $this->client->request('GET', '/show/'.self::fixtureList('User 1 main list')->getId());

        $this->client->submitForm('Archive list');
        $this->client->followRedirect();

        self::assertSelectorTextContains('h2 .badge', 'archived');
        self::assertSelectorNotExists('#task_summary');
    }

    public function testArchiveFailsIfTheListWasChangedInTheMeantime(): void
    {
        $listId = self::fixtureList('User 1 main list')->getId();

        $this->loginAs(AppFixtures::USER1_EMAIL);
        $this->client->request('GET', '/show/'.$listId);

        // Another user changes the list after the page was rendered.
        static::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE app_task_lists SET version = version + 1 WHERE id = :id',
            ['id' => $listId],
        );

        $this->client->submitForm('Archive list');
        self::assertResponseRedirects('/show/'.$listId);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'The list was changed in the meantime.');
        self::assertSelectorNotExists('h2 .badge');
    }

    public function testOwnerCanAddContributor(): void
    {
        $listId = self::fixtureList('User 1 main list')->getId();

        $this->loginAs(AppFixtures::USER1_EMAIL);
        $crawler = $this->client->request('GET', '/contributors/'.$listId);
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Add')->form();
        $form['contributor[contributor]']->select((string) self::fixtureUser(AppFixtures::USER3_EMAIL)->getId());
        $this->client->submit($form);

        self::assertResponseRedirects('/show/'.$listId);
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'with contributions from: '.AppFixtures::USER3_EMAIL);
    }

    public function testContributorsPageIsForbiddenForContributors(): void
    {
        $this->loginAs(AppFixtures::USER2_EMAIL);
        $this->client->request('GET', '/contributors/'.self::fixtureList('Shared list of user 1')->getId());

        self::assertResponseStatusCodeSame(403);
    }

    private function loginAs(string $email): void
    {
        $this->client->loginUser(self::fixtureUser($email));
    }
}
