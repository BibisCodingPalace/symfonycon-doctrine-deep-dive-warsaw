# Doctrine ORM: Deep Dive

Demo application for the Doctrine ORM Deep Dive workshop: a small todo list app
built with **Symfony 8.1** and **Doctrine ORM 3.8** (DBAL 4.6).

The project runs entirely in Docker. The app runs in a
[FrankenPHP](https://frankenphp.dev) container (PHP 8.4 + Caddy), the database
in a PostgreSQL container.

## Requirements

- [Docker](https://docs.docker.com/get-docker/) incl. Docker Compose v2
- `make` (optional, but recommended; run `make` targets from the project root on the host, not inside the app container)

## Installation

```bash
make setup
```

This builds the image, starts the stack, installs the PHP dependencies, creates
the database schema and loads the fixtures. You can also run the steps one by one:

```bash
make build     # build the application image
make up        # start the stack
make install   # composer install inside the container
make migrate   # run the Doctrine migrations
make fixtures  # load the demo data
```

Then open the app in your browser: <http://localhost:8000>

The fixtures create three users. The password is the part of the email address
before the `@`:

| Email               | Password | Data                                                    |
| ------------------- | -------- | ------------------------------------------------------- |
| `user1@example.com` | `user1`  | a main list, a list shared with user 2, an archived list |
| `user2@example.com` | `user2`  | a main list, contributes to the shared list of user 1   |
| `user3@example.com` | `user3`  | no lists                                                |

If port 8000 or 5432 is already taken on your machine, start the stack with
other host ports, e.g. `APP_PORT=8080 POSTGRES_PORT=5433 make up`.

## Running tests

```bash
make test                  # recreates the test database and runs the full suite
make run-stateless-tests   # only tests which do not write to the database
```

## Useful targets

| Target          | Description                                 |
| --------------- | ------------------------------------------- |
| `make up`       | Start the stack in the background           |
| `make down`     | Stop and remove the stack                   |
| `make logs`     | Follow container logs                       |
| `make shell`    | Open a shell inside the app container       |
| `make install`  | Run `composer install` inside the container |
| `make migrate`  | Run the Doctrine migrations                 |
| `make fixtures` | Load the fixtures (purges the database)     |

Anything else you would normally run directly goes through
`docker compose exec app …`, for example:

```bash
docker compose exec app bin/console doctrine:schema:validate
docker compose exec app bin/console make:migration
```

## The workshop tasks

`main` is the starting point: the app is clickable, but `TaskListController`
only serves hard-coded dummy data. Every task has a solution branch that builds
on the previous one, so you can always catch up or compare:

```bash
git diff main tasks/task-1    # what the solution of task 1 changes
git switch tasks/task-2       # continue from the solution of task 2
```

### Task 1 – Queries

Replace the dummy data with real data from the database.

- `TaskListRepository::findListsOwnedBy()` – all lists of a user (DQL)
- `TaskListRepository::findListsContributedBy()` – lists a user contributes to (QueryBuilder with a join)
- `TaskListRepository::findActive()` / `findArchived()` – owned lists by state
- `TaskListRepository::findSummarizedTaskListFor()` – a `SELECT NEW` query which hydrates a read model (DTO) with the number of tasks instead of full entities
- `TaskRepository::findTasksCreatedToday()` – the tasks of a list created today
- Implement the write actions of the controller (create a list, add a task, close/reopen a task, archive a list, add a contributor)
- Cover your queries with tests

Solution: `tasks/task-1`

### Task 2 – Result cache

The overview runs the same queries on every request. Configure a result cache
pool and cache the overview queries for an hour. Give every cached query its own
result cache id and check the effect in the Doctrine panel of the profiler.

A cached result does not notice changes: create a list and it is missing in the
overview. Delete the cached results whenever a list is changed, that is what the
result cache ids are for.

You can also empty the result cache by hand at any time:

```bash
docker compose exec app bin/console cache:pool:clear doctrine.result_cache_pool
```

Solution: `tasks/task-2`

### Task 3 – Event listener

Keep a simple change log: write a log entry whenever a task is changed, i.e.
closed or reopened.

- Write a Doctrine entity listener for `Task` which listens to the `postUpdate` event.
- Log with the Monolog logger, just inject `Psr\Log\LoggerInterface`. No extra table or file is needed.
- Bonus: log what has changed. The `UnitOfWork` knows the change set of the task.

You find the log entries in the "Logs" panel of the profiler and in the log file:

```bash
docker compose exec app tail -f var/log/dev.log | grep app.INFO
```

Solution: `tasks/task-3`

### Task 4 – Optimistic locking

Two users work on the same list. Make sure a list is only archived if it has not
been changed since the user loaded the page: add a `#[ORM\Version]` field,
create a migration, send the version along with the archive form and handle the
`OptimisticLockException`.

- The version column needs an explicit type: `#[ORM\Column(type: Types::INTEGER)]`.
- Try it out with two browser windows: open the same list in both, add a task in the first one and archive the list in the second one.

Solution: `tasks/task-4`

### Task 5 – Testing with Foundry

Purging the database and loading the fixtures for every test is slow.
Use [zenstruck/foundry](https://github.com/zenstruck/foundry) factories to
create exactly the data a test needs and
[dama/doctrine-test-bundle](https://github.com/dmaicher/doctrine-test-bundle)
to roll back every test in a transaction. Foundry's PHPUnit extension can
create and migrate the test database for you before the first test.

Solution: `tasks/task-5`
