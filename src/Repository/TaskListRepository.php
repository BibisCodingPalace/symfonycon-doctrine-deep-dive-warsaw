<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TaskList;
use App\Entity\User;
use App\TaskList\SummarizedTaskList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskList>
 */
class TaskListRepository extends ServiceEntityRepository
{
    public const int RESULT_CACHE_LIFETIME = 3600;

    public const string CACHE_SUMMARIZED = 'frontpage_summarized';
    public const string CACHE_OWNED = 'frontpage_owned';
    public const string CACHE_CONTRIBUTED = 'frontpage_contributed';
    public const string CACHE_ACTIVE = 'frontpage_active';
    public const string CACHE_ARCHIVED = 'frontpage_archived';

    /**
     * The ids of all cached overview queries. A cache entry holds the results for all users:
     * DBAL stores one result per combination of SQL and parameters under the same id.
     */
    public const array RESULT_CACHE_IDS = [
        self::CACHE_SUMMARIZED,
        self::CACHE_OWNED,
        self::CACHE_CONTRIBUTED,
        self::CACHE_ACTIVE,
        self::CACHE_ARCHIVED,
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskList::class);
    }

    /**
     * Deletes the cached results of all overview queries, for all users.
     */
    public function clearResultCache(): void
    {
        $this->getEntityManager()->getConfiguration()->getResultCache()?->deleteItems(self::RESULT_CACHE_IDS);
    }

    /**
     * SELECT NEW hydrates a read model instead of entities: no change tracking, no lazy loading
     * and the number of tasks is counted by the database.
     *
     * @return list<SummarizedTaskList>
     */
    public function findSummarizedTaskListFor(User $user): array
    {
        $dql = <<<'DQL'
            SELECT NEW App\TaskList\SummarizedTaskList(
                task_list.id,
                task_list.title,
                task_list.archived,
                task_list.created,
                task_list.lastUpdated,
                SIZE(task_list.items)
            )
            FROM App\Entity\TaskList task_list
            WHERE task_list.owner = :owner
            ORDER BY task_list.created ASC, task_list.id ASC
            DQL;

        return $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('owner', $user)
            ->enableResultCache(self::RESULT_CACHE_LIFETIME, self::CACHE_SUMMARIZED)
            ->getResult();
    }

    /**
     * @return list<TaskList>
     */
    public function findListsOwnedBy(User $owner): array
    {
        $dql = <<<'DQL'
            SELECT task_list
            FROM App\Entity\TaskList task_list
            WHERE task_list.owner = :owner
            ORDER BY task_list.created ASC, task_list.id ASC
            DQL;

        return $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('owner', $owner)
            ->enableResultCache(self::RESULT_CACHE_LIFETIME, self::CACHE_OWNED)
            ->getResult();
    }

    /**
     * @return list<TaskList>
     */
    public function findListsContributedBy(User $user): array
    {
        return $this->createQueryBuilder('task_list')
            ->join('task_list.contributors', 'contributor')
            ->where('contributor = :contributor')
            ->setParameter('contributor', $user)
            ->orderBy('task_list.created', \SortDirection::Ascending)
            ->addOrderBy('task_list.id', \SortDirection::Ascending)
            ->getQuery()
            ->enableResultCache(self::RESULT_CACHE_LIFETIME, self::CACHE_CONTRIBUTED)
            ->getResult();
    }

    /**
     * @return list<TaskList>
     */
    public function findActive(User $owner): array
    {
        return $this->createQueryBuilder('task_list')
            ->where('task_list.owner = :owner')
            ->andWhere('task_list.archived = false')
            ->setParameter('owner', $owner)
            ->orderBy('task_list.created', \SortDirection::Ascending)
            ->addOrderBy('task_list.id', \SortDirection::Ascending)
            ->getQuery()
            ->enableResultCache(self::RESULT_CACHE_LIFETIME, self::CACHE_ACTIVE)
            ->getResult();
    }

    /**
     * @return list<TaskList>
     */
    public function findArchived(User $owner): array
    {
        return $this->createQueryBuilder('task_list')
            ->where('task_list.owner = :owner')
            ->andWhere('task_list.archived = true')
            ->setParameter('owner', $owner)
            ->orderBy('task_list.created', \SortDirection::Ascending)
            ->addOrderBy('task_list.id', \SortDirection::Ascending)
            ->getQuery()
            ->enableResultCache(self::RESULT_CACHE_LIFETIME, self::CACHE_ARCHIVED)
            ->getResult();
    }
}
