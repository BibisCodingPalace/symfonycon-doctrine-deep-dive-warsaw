<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

/**
 * A simple change log: writes a log entry whenever a task was changed.
 *
 * An entity listener is only called for the configured entity, here after an UPDATE of a task.
 */
#[AsEntityListener(event: Events::postUpdate, entity: Task::class)]
final readonly class TaskChangeLogListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function postUpdate(Task $task, PostUpdateEventArgs $event): void
    {
        // The change set contains every changed field: ['field' => [old value, new value]]
        $changes = $event->getObjectManager()->getUnitOfWork()->getEntityChangeSet($task);

        $this->logger->info('Task "{summary}" was changed.', [
            'summary' => $task->getSummary(),
            'task' => $task->getId(),
            'changes' => $changes,
        ]);
    }
}
