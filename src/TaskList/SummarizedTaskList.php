<?php

declare(strict_types=1);

namespace App\TaskList;

use App\Repository\TaskListRepository;

/**
 * Read model for the overview, hydrated by the SELECT NEW query in
 * {@see TaskListRepository::findSummarizedTaskListFor()}.
 */
final readonly class SummarizedTaskList
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $archived,
        public \DateTimeImmutable $created,
        public ?\DateTimeImmutable $updated,
        public int $taskCount,
    ) {
    }
}
