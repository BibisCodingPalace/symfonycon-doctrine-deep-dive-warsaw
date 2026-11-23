<?php

declare(strict_types=1);

namespace App\Tests\TaskList;

use App\Entity\TaskList;
use App\Factory\TaskFactory;
use App\Factory\TaskListFactory;
use App\Factory\UserFactory;
use App\TaskList\TaskListService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Every test creates exactly the data it needs with Foundry factories.
 */
#[Group('integration')]
#[Group('stateful')]
final class TaskListServiceTest extends KernelTestCase
{
    public function testCreateTaskListPersistsListForOwner(): void
    {
        $owner = UserFactory::createOne();

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

        $this->service()->createTaskList(UserFactory::createOne(), '   ');
    }

    public function testContributorsCanAddTasks(): void
    {
        $contributor = UserFactory::createOne();
        $taskList = TaskListFactory::new()->sharedWith($contributor)->withTasks('Existing task')->create();

        $task = $this->service()->addTask($contributor, $taskList, 'Added by the contributor');

        self::assertNotNull($task->getId(), 'The task is persisted by the cascade on TaskList::$items.');
        self::assertCount(2, $taskList->getItems());
        self::assertNotNull($taskList->getLastUpdatedOn());
    }

    public function testOtherUsersCannotAddTasks(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->service()->addTask(UserFactory::createOne(), TaskListFactory::createOne(), 'Not allowed');
    }

    public function testArchivedListsCannotBeModified(): void
    {
        $taskList = TaskListFactory::new()->archived()->create();

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Archived lists cannot be modified.');

        $this->service()->addTask($taskList->getOwner(), $taskList, 'Too late');
    }

    public function testToggleTaskClosesAndReopensTask(): void
    {
        $task = TaskFactory::createOne();
        $owner = $task->getList()->getOwner();

        $this->service()->toggleTask($owner, $task);
        self::assertTrue($task->isDone());

        $this->service()->toggleTask($owner, $task);
        self::assertFalse($task->isDone());
    }

    public function testOnlyTheOwnerCanAddContributors(): void
    {
        $contributor = UserFactory::createOne();
        $taskList = TaskListFactory::new()->sharedWith($contributor)->create();

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Only the list owner can add contributors.');

        $this->service()->addContributor($contributor, $taskList, UserFactory::createOne());
    }

    public function testAddContributorStoresTheAssociation(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $taskList = TaskListFactory::createOne();
        $contributor = UserFactory::createOne();

        $this->service()->addContributor($taskList->getOwner(), $taskList, $contributor);
        $entityManager->clear();

        $reloadedList = $entityManager->find(TaskList::class, $taskList->getId());
        self::assertNotSame($taskList, $reloadedList);
        self::assertSame(
            [$contributor->getEmail()],
            array_map(static fn ($user): string => $user->getEmail(), $reloadedList->getContributors()),
        );
    }

    public function testArchiveMarksListAsArchivedAndIncrementsTheVersion(): void
    {
        $taskList = TaskListFactory::createOne();
        $version = $taskList->getVersion();

        $this->service()->archive($taskList->getOwner(), $taskList, $version);

        self::assertTrue($taskList->isArchived());
        self::assertSame($version + 1, $taskList->getVersion());
    }

    public function testArchiveFailsIfTheUserHasSeenAnOlderVersion(): void
    {
        $taskList = TaskListFactory::createOne();
        $seenVersion = $taskList->getVersion();

        $this->changeListInAnotherRequest($taskList);
        self::getContainer()->get(EntityManagerInterface::class)->refresh($taskList);

        try {
            $this->service()->archive($taskList->getOwner(), $taskList, $seenVersion);
            self::fail('Expected an OptimisticLockException.');
        } catch (OptimisticLockException) {
            self::assertFalse($taskList->isArchived(), 'The version is checked before the list is changed.');
        }
    }

    public function testArchiveFailsIfTheListIsChangedWhileTheRequestIsRunning(): void
    {
        $taskList = TaskListFactory::createOne();
        $owner = $taskList->getOwner();
        $version = $taskList->getVersion();

        // The entity in memory still has the old version, only the versioned UPDATE detects the conflict.
        $this->changeListInAnotherRequest($taskList);

        $this->expectException(OptimisticLockException::class);

        $this->service()->archive($owner, $taskList, $version);
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
