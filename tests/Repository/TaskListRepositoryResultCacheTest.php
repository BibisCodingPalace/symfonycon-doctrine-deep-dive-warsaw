<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Repository\TaskListRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Cache\CacheItemPoolInterface;

#[Group('integration')]
#[Group('stateful')]
final class TaskListRepositoryResultCacheTest extends AbstractRepositoryKernelTestCase
{
    #[TestWith(['findSummarizedTaskListFor', TaskListRepository::CACHE_SUMMARIZED])]
    #[TestWith(['findListsOwnedBy', TaskListRepository::CACHE_OWNED])]
    #[TestWith(['findListsContributedBy', TaskListRepository::CACHE_CONTRIBUTED])]
    #[TestWith(['findActive', TaskListRepository::CACHE_ACTIVE])]
    #[TestWith(['findArchived', TaskListRepository::CACHE_ARCHIVED])]
    public function testQueryStoresItsResultUnderItsCacheId(string $method, string $cacheId): void
    {
        self::assertFalse($this->resultCache()->hasItem($cacheId));

        $this->repository()->$method(self::fixtureUser(AppFixtures::USER1_EMAIL));

        self::assertTrue($this->resultCache()->hasItem($cacheId));
    }

    public function testCachedResultIsUsedInsteadOfQueryingTheDatabase(): void
    {
        $user1 = self::fixtureUser(AppFixtures::USER1_EMAIL);
        self::assertSame(2, $this->taskCountOfMainList($user1));

        // Changes made behind Doctrine's back: the next call would return 0 tasks without the cache.
        self::getContainer()->get(Connection::class)->executeStatement('DELETE FROM app_task_items');

        self::assertSame(2, $this->taskCountOfMainList($user1));
    }

    public function testOneCacheEntryHoldsTheResultsOfAllUsers(): void
    {
        $repository = $this->repository();

        self::assertCount(3, $repository->findListsOwnedBy(self::fixtureUser(AppFixtures::USER1_EMAIL)));
        self::assertCount(1, $repository->findListsOwnedBy(self::fixtureUser(AppFixtures::USER2_EMAIL)));

        $cachedResults = $this->resultCache()->getItem(TaskListRepository::CACHE_OWNED)->get();
        self::assertIsArray($cachedResults);
        self::assertCount(2, $cachedResults, 'One result per combination of SQL and parameters.');
    }

    private function taskCountOfMainList(User $user): int
    {
        $summaries = array_column($this->repository()->findSummarizedTaskListFor($user), null, 'name');

        return $summaries['User 1 main list']->taskCount;
    }

    private function repository(): TaskListRepository
    {
        return self::getContainer()->get(TaskListRepository::class);
    }

    private function resultCache(): CacheItemPoolInterface
    {
        return self::getContainer()->get('doctrine.result_cache_pool');
    }
}
