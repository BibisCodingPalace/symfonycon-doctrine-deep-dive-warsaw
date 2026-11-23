<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'app_task_items')]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private TaskList $taskList;

    #[ORM\Column(length: 255)]
    private string $summary;

    #[ORM\Column]
    private bool $done = false;

    #[ORM\Column]
    private \DateTimeImmutable $created;

    /**
     * @internal New tasks should only be created through TaskList::addItem()
     */
    public function __construct(TaskList $taskList, string $summary)
    {
        $this->taskList = $taskList;
        $this->summary = $summary;

        $this->created = new \DateTimeImmutable();
    }

    public function close(): void
    {
        if ($this->done) {
            throw new \RuntimeException('Task is already done.');
        }

        $this->done = true;
    }

    public function reopen(): void
    {
        if (!$this->done) {
            throw new \RuntimeException('Task is already open.');
        }

        $this->done = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getList(): TaskList
    {
        return $this->taskList;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    public function getCreatedOn(): \DateTimeImmutable
    {
        return $this->created;
    }
}
