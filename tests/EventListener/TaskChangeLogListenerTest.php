<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\DataFixtures\AppFixtures;
use App\TaskList\TaskListService;
use App\Tests\Support\ReloadsAppFixturesTrait;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
#[Group('stateful')]
final class TaskChangeLogListenerTest extends KernelTestCase
{
    use ReloadsAppFixturesTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        static::reloadAppFixtures();
    }

    public function testClosingAndReopeningATaskIsLogged(): void
    {
        $user = self::fixtureUser(AppFixtures::USER1_EMAIL);
        $task = self::fixtureList('User 1 main list')->getItems()[0];

        $this->service()->toggleTask($user, $task);
        $this->service()->toggleTask($user, $task);

        $entries = $this->changeLogEntries();
        self::assertCount(2, $entries);
        self::assertSame('Task "First task for user 1" was changed.', $entries[0]->message);
        self::assertSame($task->getId(), $entries[0]->context['task']);
        self::assertSame(['done' => [false, true]], $entries[0]->context['changes']);
        self::assertSame(['done' => [true, false]], $entries[1]->context['changes']);
    }

    public function testCreatingATaskIsNotLogged(): void
    {
        // A new task is written with an INSERT, postUpdate is only dispatched for UPDATEs.
        $this->service()->addTask(
            self::fixtureUser(AppFixtures::USER1_EMAIL),
            self::fixtureList('User 1 main list'),
            'A new task',
        );

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
