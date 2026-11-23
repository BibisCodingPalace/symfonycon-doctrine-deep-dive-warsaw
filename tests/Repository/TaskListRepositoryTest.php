<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\DataFixtures\AppFixtures;
use App\Entity\TaskList;
use App\Repository\TaskListRepository;
use App\TaskList\SummarizedTaskList;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
#[Group('stateful')]
final class TaskListRepositoryTest extends AbstractRepositoryKernelTestCase
{
    public function testFindListsOwnedByReturnsAllListsForOwner(): void
    {
        $lists = $this->repository()->findListsOwnedBy(self::fixtureUser(AppFixtures::USER1_EMAIL));

        self::assertSame(
            ['User 1 main list', 'Shared list of user 1', 'Archived list of user 1'],
            array_map(static fn (TaskList $list): string => $list->getTitle(), $lists),
        );
    }

    public function testFindListsOwnedByDoesNotReturnListsOfOtherUsers(): void
    {
        $lists = $this->repository()->findListsOwnedBy(self::fixtureUser(AppFixtures::USER2_EMAIL));

        self::assertCount(1, $lists);
        self::assertSame('User 2 main list', $lists[0]->getTitle());
    }

    public function testFindActiveReturnsOnlyNonArchivedListsForOwner(): void
    {
        $user1 = self::fixtureUser(AppFixtures::USER1_EMAIL);

        $lists = $this->repository()->findActive($user1);

        self::assertCount(2, $lists);
        foreach ($lists as $list) {
            self::assertFalse($list->isArchived());
            self::assertSame($user1, $list->getOwner());
        }
    }

    public function testFindArchivedReturnsOnlyArchivedListsForOwner(): void
    {
        $lists = $this->repository()->findArchived(self::fixtureUser(AppFixtures::USER1_EMAIL));

        self::assertCount(1, $lists);
        self::assertTrue($lists[0]->isArchived());
        self::assertSame('Archived list of user 1', $lists[0]->getTitle());
    }

    public function testFindListsContributedByReturnsListsWhereUserIsContributor(): void
    {
        $lists = $this->repository()->findListsContributedBy(self::fixtureUser(AppFixtures::USER2_EMAIL));

        self::assertCount(1, $lists);
        self::assertSame('Shared list of user 1', $lists[0]->getTitle());
    }

    public function testFindListsContributedByReturnsEmptyWhenUserHasNoContributions(): void
    {
        self::assertSame([], $this->repository()->findListsContributedBy(self::fixtureUser(AppFixtures::USER3_EMAIL)));
    }

    public function testFindSummarizedTaskListForReturnsDtosWithItemCounts(): void
    {
        $summaries = $this->repository()->findSummarizedTaskListFor(self::fixtureUser(AppFixtures::USER1_EMAIL));

        self::assertCount(3, $summaries);
        self::assertContainsOnlyInstancesOf(SummarizedTaskList::class, $summaries);

        $byName = array_column($summaries, null, 'name');

        self::assertSame(2, $byName['User 1 main list']->taskCount);
        self::assertSame(1, $byName['Shared list of user 1']->taskCount);
        self::assertSame(1, $byName['Archived list of user 1']->taskCount);
        self::assertFalse($byName['User 1 main list']->archived);
        self::assertTrue($byName['Archived list of user 1']->archived);
        self::assertNotNull($byName['User 1 main list']->updated);
        self::assertSame(self::fixtureList('User 1 main list')->getId(), $byName['User 1 main list']->id);
    }

    private function repository(): TaskListRepository
    {
        return self::getContainer()->get(TaskListRepository::class);
    }
}
