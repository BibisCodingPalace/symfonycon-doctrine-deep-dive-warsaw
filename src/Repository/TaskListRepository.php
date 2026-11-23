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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskList::class);
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
            ->getResult();
    }
}
