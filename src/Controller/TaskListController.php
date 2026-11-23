<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Task;
use App\Entity\TaskList;
use App\Entity\User;
use App\Form\ContributorType;
use App\Repository\TaskListRepository;
use App\Repository\TaskRepository;
use App\TaskList\TaskListService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(name: 'tasklist_')]
final class TaskListController extends AbstractController
{
    public function __construct(
        private readonly TaskListRepository $taskListRepository,
        private readonly TaskRepository $taskRepository,
        private readonly TaskListService $taskListService,
    ) {
    }

    #[Route('/', name: 'list', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, #[MapQueryParameter] ?string $filter = null): Response
    {
        if (null === $filter) {
            return $this->render('tasks/index_summarized.html.twig', [
                'lists' => $this->taskListRepository->findSummarizedTaskListFor($user),
            ]);
        }

        $taskLists = match ($filter) {
            'own' => $this->taskListRepository->findListsOwnedBy($user),
            'contributing' => $this->taskListRepository->findListsContributedBy($user),
            'active' => $this->taskListRepository->findActive($user),
            'archived' => $this->taskListRepository->findArchived($user),
            default => throw $this->createNotFoundException(sprintf('Unknown filter "%s".', $filter)),
        };

        return $this->render('tasks/index.html.twig', [
            'task_lists' => $taskLists,
        ]);
    }

    #[Route('/', name: 'create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): Response
    {
        try {
            $taskList = $this->taskListService->createTaskList($user, $request->request->getString('name'));
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirectToRoute('tasklist_list');
        }

        return $this->redirectToRoute('tasklist_show', ['id' => $taskList->getId()]);
    }

    #[Route('/show/{id}', name: 'show', methods: ['GET'])]
    public function show(TaskList $taskList, #[CurrentUser] User $user): Response
    {
        $this->denyAccessUnlessMember($taskList, $user);

        return $this->render('tasks/show.html.twig', [
            'task_list' => $taskList,
        ]);
    }

    #[Route('/recent/{id}', name: 'recent', methods: ['GET'])]
    public function recent(TaskList $taskList, #[CurrentUser] User $user): Response
    {
        $this->denyAccessUnlessMember($taskList, $user);

        return $this->render('tasks/recent.html.twig', [
            'task_list' => $taskList,
            'tasks' => $this->taskRepository->findTasksCreatedToday($taskList),
        ]);
    }

    #[Route('/add/{id}', name: 'add', methods: ['POST'])]
    public function add(Request $request, TaskList $taskList, #[CurrentUser] User $user): Response
    {
        try {
            $this->taskListService->addTask($user, $taskList, $request->request->getString('summary'));
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('tasklist_show', ['id' => $taskList->getId()]);
    }

    #[Route('/update/{id}', name: 'item_update', methods: ['POST'])]
    public function update(Task $task, #[CurrentUser] User $user): Response
    {
        $this->taskListService->toggleTask($user, $task);

        return $this->redirectToRoute('tasklist_show', ['id' => $task->getList()->getId()]);
    }

    #[Route('/archive/{id}', name: 'archive', methods: ['POST'])]
    public function archive(TaskList $taskList, #[CurrentUser] User $user): Response
    {
        $this->taskListService->archive($user, $taskList);

        return $this->redirectToRoute('tasklist_show', ['id' => $taskList->getId()]);
    }

    #[Route('/contributors/{id}', name: 'contributors', methods: ['GET', 'POST'])]
    public function contributors(Request $request, TaskList $taskList, #[CurrentUser] User $user): Response
    {
        if (!$taskList->isOwnedBy($user)) {
            throw $this->createAccessDeniedException('Only the list owner can add contributors.');
        }

        $form = $this->createForm(ContributorType::class, null, ['list' => $taskList]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contributor = $form->get('contributor')->getData();

            if ($contributor instanceof User) {
                $this->taskListService->addContributor($user, $taskList, $contributor);
            } else {
                $this->addFlash('danger', 'Please select a user.');
            }

            return $this->redirectToRoute('tasklist_show', ['id' => $taskList->getId()]);
        }

        return $this->render('tasks/contributors.html.twig', [
            'task_list' => $taskList,
            'form' => $form,
        ]);
    }

    private function denyAccessUnlessMember(TaskList $taskList, User $user): void
    {
        if (!$taskList->isAccessibleBy($user)) {
            throw $this->createAccessDeniedException('You are not a member of this list.');
        }
    }
}
