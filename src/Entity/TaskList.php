<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TaskListRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskListRepository::class)]
#[ORM\Table(name: 'app_task_lists')]
class TaskList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column]
    private bool $archived = false;

    /**
     * @var Collection<int, Task>
     */
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'taskList', cascade: ['persist'])]
    private Collection $items;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    private Collection $contributors;

    #[ORM\Column]
    private \DateTimeImmutable $created;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUpdated = null;

    public function __construct(User $owner, string $title)
    {
        $this->owner = $owner;
        $this->title = $title;

        $this->created = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
        $this->contributors = new ArrayCollection();
    }

    public function addItem(string $summary): Task
    {
        $task = new Task($this, $summary);

        $this->items->add($task);
        $this->lastUpdated = new \DateTimeImmutable();

        return $task;
    }

    public function addContributor(User $user): void
    {
        if (!$this->contributors->contains($user)) {
            $this->contributors->add($user);
        }
    }

    public function archive(): void
    {
        $this->archived = true;
        $this->lastUpdated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    /**
     * @return list<Task>
     */
    public function getItems(): array
    {
        return $this->items->getValues();
    }

    /**
     * @return list<User>
     */
    public function getContributors(): array
    {
        return $this->contributors->getValues();
    }

    public function getCreatedOn(): \DateTimeImmutable
    {
        return $this->created;
    }

    public function getLastUpdatedOn(): ?\DateTimeImmutable
    {
        return $this->lastUpdated;
    }
}
