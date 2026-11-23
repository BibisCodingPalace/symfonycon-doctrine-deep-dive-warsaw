<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;
use App\Entity\TaskList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * @return list<Task>
     */
    public function findTasksCreatedToday(TaskList $taskList): array
    {
        $dql = <<<'DQL'
            SELECT task
            FROM App\Entity\Task task
            WHERE task.taskList = :taskList
            AND task.created BETWEEN :startOfDay AND :now
            ORDER BY task.created ASC, task.id ASC
            DQL;

        return $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('taskList', $taskList)
            ->setParameter('startOfDay', new \DateTimeImmutable('today'), Types::DATETIME_IMMUTABLE)
            ->setParameter('now', new \DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
            ->getResult();
    }
}
