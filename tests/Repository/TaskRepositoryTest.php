<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Task;
use App\Repository\TaskRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
#[Group('stateful')]
final class TaskRepositoryTest extends AbstractRepositoryKernelTestCase
{
    public function testFindTasksCreatedTodayReturnsTheTasksOfTheList(): void
    {
        $tasks = $this->repository()->findTasksCreatedToday(self::fixtureList('User 1 main list'));

        self::assertSame(
            ['First task for user 1', 'Second task for user 1'],
            array_map(static fn (Task $task): string => $task->getSummary(), $tasks),
        );
    }

    public function testFindTasksCreatedTodayIgnoresOlderTasks(): void
    {
        self::getContainer()->get(Connection::class)->executeStatement(
            "UPDATE app_task_items SET created = created - INTERVAL '1 day' WHERE summary = :summary",
            ['summary' => 'First task for user 1'],
        );

        $tasks = $this->repository()->findTasksCreatedToday(self::fixtureList('User 1 main list'));

        self::assertCount(1, $tasks);
        self::assertSame('Second task for user 1', $tasks[0]->getSummary());
    }

    private function repository(): TaskRepository
    {
        return self::getContainer()->get(TaskRepository::class);
    }
}
