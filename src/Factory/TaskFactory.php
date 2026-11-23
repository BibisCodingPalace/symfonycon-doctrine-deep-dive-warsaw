<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Task;
use App\Entity\TaskList;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Task>
 */
final class TaskFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Task::class;
    }

    protected function defaults(): array
    {
        return [
            'taskList' => TaskListFactory::new(),
            'summary' => self::faker()->sentence(4),
        ];
    }

    protected function initialize(): static
    {
        // Tasks are created through their list, which keeps both sides of the association in sync.
        return $this->instantiateWith(Instantiator::use(
            static fn (TaskList $taskList, string $summary): Task => $taskList->addItem($summary),
        ));
    }

    public function done(): static
    {
        return $this->afterInstantiate(static function (Task $task): void {
            $task->close();
        });
    }
}
