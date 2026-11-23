<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\TaskList;
use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<TaskList>
 */
final class TaskListFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return TaskList::class;
    }

    protected function defaults(): array
    {
        return [
            'owner' => UserFactory::new(),
            'title' => self::faker()->sentence(3),
        ];
    }

    public function archived(): static
    {
        return $this->afterInstantiate(static function (TaskList $taskList): void {
            $taskList->archive();
        });
    }

    public function sharedWith(User ...$contributors): static
    {
        return $this->afterInstantiate(static function (TaskList $taskList) use ($contributors): void {
            foreach ($contributors as $contributor) {
                $taskList->addContributor($contributor);
            }
        });
    }

    /**
     * The tasks are persisted by the cascade on TaskList::$items.
     */
    public function withTasks(string ...$summaries): static
    {
        return $this->afterInstantiate(static function (TaskList $taskList) use ($summaries): void {
            foreach ($summaries as $summary) {
                $taskList->addItem($summary);
            }
        });
    }
}
