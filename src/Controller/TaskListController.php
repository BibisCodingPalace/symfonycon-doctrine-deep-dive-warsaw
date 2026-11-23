<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Starting point of the workshop: all actions work on hard-coded dummy data.
 *
 * Task 1 replaces the dummy data with real Doctrine queries, see README.md.
 */
#[Route(name: 'tasklist_')]
final class TaskListController extends AbstractController
{
    #[Route('/', name: 'list', methods: ['GET'])]
    public function index(): Response
    {
        // TODO Task 1: load the lists of the logged-in user, depending on the "filter" query parameter:
        //   (none)       => summary (DTO) of the lists owned by the user
        //   own          => lists owned by the user
        //   contributing => lists the user contributes to
        //   active       => owned lists which are not archived
        //   archived     => owned lists which are archived
        return $this->render('tasks/index.html.twig', [
            'task_lists' => self::dummyTaskLists(),
        ]);
    }

    #[Route('/', name: 'create', methods: ['POST'])]
    public function create(): Response
    {
        // TODO Task 1: create a new TaskList for the logged-in user and redirect to it.
        $this->addFlash('warning', 'Creating lists is not implemented yet.');

        return $this->redirectToRoute('tasklist_list');
    }

    #[Route('/show/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        // TODO Task 1: load the TaskList from the database.
        return $this->render('tasks/show.html.twig', [
            'task_list' => self::dummyTaskLists()[$id] ?? throw $this->createNotFoundException('Task list not found.'),
        ]);
    }

    #[Route('/recent/{id}', name: 'recent', methods: ['GET'])]
    public function recent(int $id): Response
    {
        // TODO Task 1: load the tasks of this list which were created today.
        $taskList = self::dummyTaskLists()[$id] ?? throw $this->createNotFoundException('Task list not found.');

        return $this->render('tasks/recent.html.twig', [
            'task_list' => $taskList,
            'tasks' => $taskList['items'],
        ]);
    }

    #[Route('/add/{id}', name: 'add', methods: ['POST'])]
    public function add(int $id): Response
    {
        // TODO Task 1: add a new task to the list.
        $this->addFlash('warning', 'Adding tasks is not implemented yet.');

        return $this->redirectToRoute('tasklist_show', ['id' => $id]);
    }

    #[Route('/update/{id}', name: 'item_update', methods: ['POST'])]
    public function update(int $id): Response
    {
        // TODO Task 1: close or reopen the task and redirect to its list ($id is the id of the task).
        $this->addFlash('warning', 'Closing and reopening tasks is not implemented yet.');

        return $this->redirectToRoute('tasklist_list');
    }

    #[Route('/archive/{id}', name: 'archive', methods: ['POST'])]
    public function archive(int $id): Response
    {
        // TODO Task 1: archive the list.
        $this->addFlash('warning', 'Archiving lists is not implemented yet.');

        return $this->redirectToRoute('tasklist_show', ['id' => $id]);
    }

    #[Route('/contributors/{id}', name: 'contributors', methods: ['GET', 'POST'])]
    public function contributors(int $id): Response
    {
        // TODO Task 1: render and handle App\Form\ContributorType for the list.
        $this->addFlash('warning', 'Adding contributors is not implemented yet.');

        return $this->redirectToRoute('tasklist_show', ['id' => $id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function dummyTaskLists(): array
    {
        $now = new \DateTimeImmutable();
        $owner = ['email' => 'jane@example.com'];

        return [
            1 => [
                'id' => 1,
                'owner' => $owner,
                'contributors' => [],
                'title' => 'Empty test list',
                'lastUpdatedOn' => null,
                'createdOn' => $now,
                'items' => [],
                'archived' => false,
            ],
            2 => [
                'id' => 2,
                'owner' => $owner,
                'contributors' => [],
                'title' => 'Archived test list',
                'lastUpdatedOn' => $now,
                'createdOn' => $now,
                'items' => [],
                'archived' => true,
            ],
            3 => [
                'id' => 3,
                'owner' => $owner,
                'contributors' => [],
                'title' => 'Test list with 1 open item',
                'lastUpdatedOn' => $now,
                'createdOn' => $now,
                'items' => [
                    ['id' => 1, 'summary' => 'Replace with actual data', 'done' => false],
                ],
                'archived' => false,
            ],
            4 => [
                'id' => 4,
                'owner' => $owner,
                'contributors' => [['email' => 'john@example.com']],
                'title' => 'Test list with open and closed item',
                'lastUpdatedOn' => $now,
                'createdOn' => $now,
                'items' => [
                    ['id' => 2, 'summary' => 'Create dummy data', 'done' => true],
                    ['id' => 3, 'summary' => 'Replace with actual data', 'done' => false],
                ],
                'archived' => false,
            ],
        ];
    }
}
