<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Factory\TaskFactory;
use App\Factory\TaskListFactory;
use App\TaskList\TaskListService;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
#[Group('stateful')]
final class TaskChangeLogListenerTest extends KernelTestCase
{
    public function testClosingAndReopeningATaskIsLogged(): void
    {
        $task = TaskFactory::createOne(['summary' => 'Buy milk']);
        $owner = $task->getList()->getOwner();

        $this->service()->toggleTask($owner, $task);
        $this->service()->toggleTask($owner, $task);

        $entries = $this->changeLogEntries();
        self::assertCount(2, $entries);
        self::assertSame('Task "Buy milk" was changed.', $entries[0]->message);
        self::assertSame($task->getId(), $entries[0]->context['task']);
        self::assertSame(['done' => [false, true]], $entries[0]->context['changes']);
        self::assertSame(['done' => [true, false]], $entries[1]->context['changes']);
    }

    public function testCreatingATaskIsNotLogged(): void
    {
        $taskList = TaskListFactory::createOne();

        // A new task is written with an INSERT, postUpdate is only dispatched for UPDATEs.
        $this->service()->addTask($taskList->getOwner(), $taskList, 'A new task');

        self::assertSame([], $this->changeLogEntries());
    }

    /**
     * @return list<LogRecord>
     */
    private function changeLogEntries(): array
    {
        /** @var TestHandler $logs */
        $logs = self::getContainer()->get('monolog.handler.test');

        return array_values(array_filter(
            $logs->getRecords(),
            static fn (LogRecord $record): bool => str_ends_with($record->message, 'was changed.'),
        ));
    }

    private function service(): TaskListService
    {
        return self::getContainer()->get(TaskListService::class);
    }
}
