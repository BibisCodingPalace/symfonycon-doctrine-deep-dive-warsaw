<?php

declare(strict_types=1);

namespace App\Tests\TaskList;

use App\DataFixtures\AppFixtures;
use App\Entity\TaskList;
use App\TaskList\TaskListService;
use App\Tests\Support\ReloadsAppFixturesTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Group('integration')]
#[Group('stateful')]
final class TaskListServiceTest extends KernelTestCase
{
    use ReloadsAppFixturesTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        static::reloadAppFixtures();
    }

    public function testCreateTaskListPersistsListForOwner(): void
    {
        $owner = self::fixtureUser(AppFixtures::USER3_EMAIL);

        $taskList = $this->service()->createTaskList($owner, '  Groceries ');

        self::assertNotNull($taskList->getId());
        self::assertSame('Groceries', $taskList->getTitle());
        self::assertSame($owner, $taskList->getOwner());
        self::assertFalse($taskList->isArchived());
    }

    public function testCreateTaskListRejectsEmptyTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('List title cannot be empty.');

        $this->service()->createTaskList(self::fixtureUser(AppFixtures::USER3_EMAIL), '   ');
    }

    public function testContributorsCanAddTasks(): void
    {
        $taskList = self::fixtureList('Shared list of user 1');

        $task = $this->service()->addTask(self::fixtureUser(AppFixtures::USER2_EMAIL), $taskList, 'Added by user 2');

        self::assertNotNull($task->getId(), 'The task is persisted by the cascade on TaskList::$items.');
        self::assertCount(2, $taskList->getItems());
        self::assertNotNull($taskList->getLastUpdatedOn());
    }

    public function testOtherUsersCannotAddTasks(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->service()->addTask(
            self::fixtureUser(AppFixtures::USER3_EMAIL),
            self::fixtureList('Shared list of user 1'),
            'Not allowed',
        );
    }

    public function testArchivedListsCannotBeModified(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Archived lists cannot be modified.');

        $this->service()->addTask(
            self::fixtureUser(AppFixtures::USER1_EMAIL),
            self::fixtureList('Archived list of user 1'),
            'Too late',
        );
    }

    public function testToggleTaskClosesAndReopensTask(): void
    {
        $owner = self::fixtureUser(AppFixtures::USER1_EMAIL);
        $task = self::fixtureList('User 1 main list')->getItems()[0];

        $this->service()->toggleTask($owner, $task);
        self::assertTrue($task->isDone());

        $this->service()->toggleTask($owner, $task);
        self::assertFalse($task->isDone());
    }

    public function testOnlyTheOwnerCanAddContributors(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Only the list owner can add contributors.');

        $this->service()->addContributor(
            self::fixtureUser(AppFixtures::USER2_EMAIL),
            self::fixtureList('Shared list of user 1'),
            self::fixtureUser(AppFixtures::USER3_EMAIL),
        );
    }

    public function testAddContributorStoresTheAssociation(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $taskList = self::fixtureList('User 1 main list');

        $this->service()->addContributor(
            self::fixtureUser(AppFixtures::USER1_EMAIL),
            $taskList,
            self::fixtureUser(AppFixtures::USER3_EMAIL),
        );
        $entityManager->clear();

        $reloaded = self::fixtureList('User 1 main list');
        self::assertNotSame($taskList, $reloaded);
        self::assertTrue($reloaded->isAccessibleBy(self::fixtureUser(AppFixtures::USER3_EMAIL)));
    }

    public function testArchiveMarksListAsArchivedAndIncrementsTheVersion(): void
    {
        $taskList = self::fixtureList('User 1 main list');
        $version = $taskList->getVersion();

        $this->service()->archive(self::fixtureUser(AppFixtures::USER1_EMAIL), $taskList, $version);

        self::assertTrue($taskList->isArchived());
        self::assertSame($version + 1, $taskList->getVersion());
    }

    public function testArchiveFailsIfTheUserHasSeenAnOlderVersion(): void
    {
        $taskList = self::fixtureList('User 1 main list');
        $seenVersion = $taskList->getVersion();

        $this->changeListInAnotherRequest($taskList);
        self::getContainer()->get(EntityManagerInterface::class)->refresh($taskList);

        try {
            $this->service()->archive(self::fixtureUser(AppFixtures::USER1_EMAIL), $taskList, $seenVersion);
            self::fail('Expected an OptimisticLockException.');
        } catch (OptimisticLockException) {
            self::assertFalse($taskList->isArchived(), 'The version is checked before the list is changed.');
        }
    }

    public function testArchiveFailsIfTheListIsChangedWhileTheRequestIsRunning(): void
    {
        $taskList = self::fixtureList('User 1 main list');

        // The entity in memory still has the old version, only the versioned UPDATE detects the conflict.
        $this->changeListInAnotherRequest($taskList);

        $this->expectException(OptimisticLockException::class);

        $this->service()->archive(self::fixtureUser(AppFixtures::USER1_EMAIL), $taskList, $taskList->getVersion());
    }

    /**
     * Simulates a concurrent request by changing the row behind the back of the EntityManager.
     */
    private function changeListInAnotherRequest(TaskList $taskList): void
    {
        self::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE app_task_lists SET version = version + 1 WHERE id = :id',
            ['id' => $taskList->getId()],
        );
    }

    private function service(): TaskListService
    {
        return self::getContainer()->get(TaskListService::class);
    }
}
