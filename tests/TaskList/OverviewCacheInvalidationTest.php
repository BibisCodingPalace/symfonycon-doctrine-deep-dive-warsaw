<?php

declare(strict_types=1);

namespace App\Tests\TaskList;

use App\DataFixtures\AppFixtures;
use App\Repository\TaskListRepository;
use App\TaskList\TaskListService;
use App\Tests\Repository\AbstractRepositoryKernelTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Every test first fills the result cache and then changes the data with the service:
 * without deleting the cached results the second query would return outdated data.
 */
#[Group('integration')]
#[Group('stateful')]
final class OverviewCacheInvalidationTest extends AbstractRepositoryKernelTestCase
{
    public function testCreatingAListClearsTheCache(): void
    {
        $user3 = self::fixtureUser(AppFixtures::USER3_EMAIL);
        self::assertCount(0, $this->repository()->findListsOwnedBy($user3));
        self::assertCount(0, $this->repository()->findSummarizedTaskListFor($user3));

        $this->service()->createTaskList($user3, 'First list of user 3');

        self::assertCount(1, $this->repository()->findListsOwnedBy($user3));
        self::assertCount(1, $this->repository()->findSummarizedTaskListFor($user3));
    }

    public function testAddingATaskClearsTheCache(): void
    {
        $user1 = self::fixtureUser(AppFixtures::USER1_EMAIL);
        self::assertSame(2, $this->taskCountOfMainList());

        $this->service()->addTask($user1, self::fixtureList('User 1 main list'), 'Third task for user 1');

        self::assertSame(3, $this->taskCountOfMainList());
    }

    public function testAddingAContributorClearsTheCache(): void
    {
        $user3 = self::fixtureUser(AppFixtures::USER3_EMAIL);
        self::assertCount(0, $this->repository()->findListsContributedBy($user3));

        $this->service()->addContributor(
            self::fixtureUser(AppFixtures::USER1_EMAIL),
            self::fixtureList('User 1 main list'),
            $user3,
        );

        self::assertCount(1, $this->repository()->findListsContributedBy($user3));
    }

    public function testArchivingAListClearsTheCache(): void
    {
        $user1 = self::fixtureUser(AppFixtures::USER1_EMAIL);
        self::assertCount(2, $this->repository()->findActive($user1));
        self::assertCount(1, $this->repository()->findArchived($user1));

        $taskList = self::fixtureList('User 1 main list');
        $this->service()->archive($user1, $taskList, $taskList->getVersion());

        self::assertCount(1, $this->repository()->findActive($user1));
        self::assertCount(2, $this->repository()->findArchived($user1));
    }

    private function taskCountOfMainList(): int
    {
        $summaries = array_column(
            $this->repository()->findSummarizedTaskListFor(self::fixtureUser(AppFixtures::USER1_EMAIL)),
            null,
            'name',
        );

        return $summaries['User 1 main list']->taskCount;
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
