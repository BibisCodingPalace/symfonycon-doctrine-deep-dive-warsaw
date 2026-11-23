<?php

declare(strict_types=1);

namespace App\TaskList;

use App\Entity\Task;
use App\Entity\TaskList;
use App\Entity\User;
use App\Repository\TaskListRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * All write operations on task lists.
 *
 * The overview queries are cached (see TaskListRepository), so every change which is visible
 * in the overview has to delete the cached results after it was written.
 *
 * Invalid input is reported with an \InvalidArgumentException, missing permissions with an AccessDeniedException.
 */
final readonly class TaskListService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskListRepository $taskListRepository,
    ) {
    }

    public function createTaskList(User $owner, string $title): TaskList
    {
        $title = trim($title);
        if ('' === $title) {
            throw new \InvalidArgumentException('List title cannot be empty.');
        }

        $taskList = new TaskList($owner, $title);
        $this->entityManager->persist($taskList);
        $this->entityManager->flush();
        $this->taskListRepository->clearResultCache();

        return $taskList;
    }

    public function addTask(User $actor, TaskList $taskList, string $summary): Task
    {
        $this->assertCanBeModifiedBy($actor, $taskList);

        $summary = trim($summary);
        if ('' === $summary) {
            throw new \InvalidArgumentException('Task summary cannot be empty.');
        }

        // The new task is persisted by the cascade on TaskList::$items.
        $task = $taskList->addItem($summary);
        $this->entityManager->flush();
        $this->taskListRepository->clearResultCache();

        return $task;
    }

    /**
     * Closes an open task and reopens a closed one.
     */
    public function toggleTask(User $actor, Task $task): void
    {
        $this->assertCanBeModifiedBy($actor, $task->getList());

        if ($task->isDone()) {
            $task->reopen();
        } else {
            $task->close();
        }

        $this->entityManager->flush();
    }

    public function addContributor(User $actor, TaskList $taskList, User $contributor): void
    {
        if ($taskList->isArchived()) {
            throw new AccessDeniedException('Archived lists cannot be modified.');
        }

        if (!$taskList->isOwnedBy($actor)) {
            throw new AccessDeniedException('Only the list owner can add contributors.');
        }

        if ($taskList->isOwnedBy($contributor)) {
            throw new \InvalidArgumentException('The owner is already a member of this list.');
        }

        $taskList->addContributor($contributor);
        $this->entityManager->flush();
        $this->taskListRepository->clearResultCache();
    }

    public function archive(User $actor, TaskList $taskList): void
    {
        $this->assertCanBeModifiedBy($actor, $taskList);

        $taskList->archive();
        $this->entityManager->flush();
        $this->taskListRepository->clearResultCache();
    }

    private function assertCanBeModifiedBy(User $actor, TaskList $taskList): void
    {
        if ($taskList->isArchived()) {
            throw new AccessDeniedException('Archived lists cannot be modified.');
        }

        if (!$taskList->isAccessibleBy($actor)) {
            throw new AccessDeniedException('You cannot edit this list.');
        }
    }
}
