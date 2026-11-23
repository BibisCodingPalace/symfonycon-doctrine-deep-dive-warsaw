<?php

declare(strict_types=1);

namespace App\Tests\TaskList;

use App\Entity\User;
use App\Factory\TaskListFactory;
use App\Factory\UserFactory;
use App\Repository\TaskListRepository;
use App\TaskList\TaskListService;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Every test first fills the result cache and then changes the data with the service:
 * without deleting the cached results the second query would return outdated data.
 */
#[Group('integration')]
#[Group('stateful')]
final class OverviewCacheInvalidationTest extends KernelTestCase
{
    public function testCreatingAListClearsTheCache(): void
    {
        $user = UserFactory::createOne();
        self::assertCount(0, $this->repository()->findListsOwnedBy($user));
        self::assertCount(0, $this->repository()->findSummarizedTaskListFor($user));

        $this->service()->createTaskList($user, 'My first list');

        self::assertCount(1, $this->repository()->findListsOwnedBy($user));
        self::assertCount(1, $this->repository()->findSummarizedTaskListFor($user));
    }

    public function testAddingATaskClearsTheCache(): void
    {
        $taskList = TaskListFactory::new()->withTasks('First task', 'Second task')->create();
        $owner = $taskList->getOwner();
        self::assertSame(2, $this->taskCountOfOnlyList($owner));

        $this->service()->addTask($owner, $taskList, 'Third task');

        self::assertSame(3, $this->taskCountOfOnlyList($owner));
    }

    public function testAddingAContributorClearsTheCache(): void
    {
        $taskList = TaskListFactory::createOne();
        $contributor = UserFactory::createOne();
        self::assertCount(0, $this->repository()->findListsContributedBy($contributor));

        $this->service()->addContributor($taskList->getOwner(), $taskList, $contributor);

        self::assertCount(1, $this->repository()->findListsContributedBy($contributor));
    }

    public function testArchivingAListClearsTheCache(): void
    {
        $taskList = TaskListFactory::createOne();
        $owner = $taskList->getOwner();
        self::assertCount(1, $this->repository()->findActive($owner));
        self::assertCount(0, $this->repository()->findArchived($owner));

        $this->service()->archive($owner, $taskList, $taskList->getVersion());

        self::assertCount(0, $this->repository()->findActive($owner));
        self::assertCount(1, $this->repository()->findArchived($owner));
    }

    private function taskCountOfOnlyList(User $owner): int
    {
        $summaries = $this->repository()->findSummarizedTaskListFor($owner);
        self::assertCount(1, $summaries);

        return $summaries[0]->taskCount;
    }

    private function repository(): TaskListRepository
    {
        return self::getContainer()->get(TaskListRepository::class);
    }

    private function service(): TaskListService
    {
        return self::getContainer()->get(TaskListService::class);
    }
}
