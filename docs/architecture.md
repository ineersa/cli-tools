# Architectural Overview

This document explains the high-level architecture of the CLI/TUI application, with a focus on the event loop, timers, background consumers/workers, and how polling integrates everything. It is intended as a starting point to understand how the system runs and where to extend it.

## Entry Point: AiClientCommand

The application is launched via the Symfony console command:

- Class: `App\Command\AiClientCommand`
- Command name: `ai:client`

On execution it:
- Puts the terminal into alternate screen and raw mode (no default echo, hidden cursor).
- Starts background Messenger consumers as separate OS processes:
  - `ConsumerAsyncWorker` (queue: `async`)
  - `ConsumerSummaryWorker` (queue: `summary`)
- Calls `Agent::cleanUpChat()` to reset state from previous sessions.
- Boots the loop via `LoopRunner::boot()` and then continuously:
  - `LoopRunner::tick()` to execute due timers.
  - `LoopRunner::sleep(1, 8)` to sleep until the next task is due (bounded by min/max ms).
- On exit or error, restores the terminal and cleans up.

## Core Loop

The core loop is minimal and explicit, implemented in `App\Tui\Loop`:

- `LoopRunner` holds a `Scheduler` and an iterable of timer providers discovered by the service tag `app.tui.loop.timer_provider` (see `config/services.yaml`).
- `LoopRunner::boot()` calls `register($scheduler)` on each timer provider so that providers add periodic tasks to the scheduler.
- `Scheduler` runs lightweight periodic timers:
  - `addPeriodic(intervalMs, callback)` schedules a callback every interval.
  - `tick()` runs all callbacks that are due.
  - `sleepUntilNextDue(minFloorMs, maxCeilMs)` computes the next due time and sleeps appropriately (using `usleep`).

This pattern yields a simple, predictable loop without hidden concurrency: all repeating tasks are registered up front by providers and executed in the main thread during `tick()`.

## Timers: UI, Consumers, Workers

Timers are contributed via classes implementing `TimerProviderInterface` and tagged with `app.tui.loop.timer_provider`. The three key timers are:

1) UI timer — `UiTimerProvider`
- Interval: ~16ms (~60 FPS)
- Responsibilities:
  - `Application::listenTerminalEvents()`: Poll terminal events (keypresses, etc.) and update internal state.
  - Render: Build the current TUI layout and draw it using PhpTui.
  - Cursor placement: Compute caret position for the multiline input box and move the cursor accordingly.
- Rendering stack:
  - `App\Tui\Application` orchestrates layout and interactions.
  - Uses PhpTui (Terminal + Widgets + BDF extension) for rendering.

2) Consumer poll timer — `ConsumerPollTimerProvider`
- Interval: 1000ms
- Responsibilities:
  - `Agent::pollConsumers()` polls health/status of background consumer processes.
  - On issues (e.g., restart), it raises a `ProblemComponent` in the TUI (a “dynamic island” style notification).

3) Worker poll timer — `WorkerPollTimerProvider`
- Interval: 250ms
- Responsibilities:
  - `Agent::pollWorkers()` polls currently active foreground workers bound to the running session (see below for worker types).
  - On errors, shows `ProblemComponent` in the TUI.

## Consumers vs Workers

- Consumers (background, async):
  - Implemented as separate OS processes started by the command.
  - Classes: `ConsumerAsyncWorker`, `ConsumerSummaryWorker`.
  - They run `bin/console messenger:consume <transport>` and keep running independently of the main loop.
  - Transports/queues are defined in `config/packages/messenger.yaml`.
    - `async` (default transport for messages like `App\Message\QuestionReceivedMessage`).
    - `summary` (used for summary-related tasks).
  - The loop polls them periodically via the Consumer timer to ensure they are alive; if a process dies, it is re-started and a problem is surfaced to the UI.

- Workers (interactive, TUI-aware):
  - Represent tasks that must integrate with the user interface (write to TUI, update state/content synchronously with the user’s session).
  - Attached/detached through the `Agent` and polled via the Worker timer.
  - If a worker terminates or fails, the Agent can detach it and the UI can surface an issue.

In code, both Consumers and Workers implement `WorkerInterface`, and the `Agent` keeps two registries: `activeWorkers` and `consumers`. Consumers are long-lived processes; workers are per-request/session tasks that need frequent polling to drive UI updates.

## Polling Model

Polling is cooperative and timer-driven:
- The main thread never blocks on long I/O; instead, it ticks timers frequently.
- Each timer’s callback is expected to be fast and non-blocking (or minimally blocking):
  - UI timer does event polling + rendering.
  - Worker/Consumer timers call light health checks or step functions.
- `Scheduler::sleepUntilNextDue()` calculates the time until the nearest next timer and sleeps for that duration (bounded to prevent tight loops).

This keeps the UI responsive while allowing background processes (workers) to proceed.

## Startup and Shutdown Sequence

Startup (AiClientCommand):
1. Configure terminal (alternate screen, hide cursor, raw mode).
2. Start background consumer processes and register them with the Agent.
3. `Agent::cleanUpChat()` to reset any prior open chat/session state.
4. Boot loop: register all timers with `LoopRunner::boot()`.
5. Enter infinite loop: `tick()` then `sleep()`.

Shutdown (finally block):
- `Agent::cleanUpChat()` resets active chat/session state.
- Terminal raw mode is disabled, alternate screen is turned off, cursor is shown, and screen is cleared.

Errors:
- Exceptions are logged; `ProblemException`s are converted into UI components where applicable (dynamic island messages).

## Messaging and Queues (Symfony Messenger)

- Transports defined in `config/packages/messenger.yaml`:
  - `async`: main queue for application work (with retry strategy).
  - `summary`: separate queue for summary generation (with retry strategy).
  - `failed`: Doctrine transport to store failed messages.
- In tests (`APP_ENV=test`), the `async` transport is in-memory to keep tests fast and deterministic.
- Background consumer processes are launched by `ConsumerAsyncWorker` and `ConsumerSummaryWorker` using the corresponding transport names.

## User Interaction Flow (High-Level)

1. The user types into the TUI input box; input is handled by `Application::listenTerminalEvents()`.
2. On Ctrl+D (submit):
   - If the line starts with `/`, it is treated as an internal command and executed by the TUI runner.
   - Otherwise, a `QuestionReceivedEvent` is dispatched and a user card is appended to the content pane.
3. Downstream of this event, a message can be enqueued to Messenger (e.g., `QuestionReceivedMessage`) and processed by handlers/consumers. Handlers may emit further messages such as `AssistantResponseReceived`, which update the TUI via state changes/components.
4. The Worker and Consumer timers keep integrating results back into the TUI and ensuring background processes are alive.

## Extending the Loop: Adding a Timer

To add a new periodic task:
1. Create a class that implements `TimerProviderInterface` with a `register(Scheduler $scheduler)` method.
2. In `register`, call `$scheduler->addPeriodic(<intervalMs>, function () { /* do work */ });`.
3. Ensure the class is auto-registered with the tag `app.tui.loop.timer_provider` (this is automatic if it implements the interface because of `config/services.yaml` `_instanceof` section).
4. The new timer will be bootstrapped automatically by `LoopRunner` on startup.

## Notes on Performance & Responsiveness

- The UI timer runs at ~60 FPS; keep its callback efficient and avoid heavy synchronous work there.
- Worker and Consumer timers are relatively low frequency (250ms and 1000ms) to balance responsiveness with CPU usage.
- If a timer needs a different cadence, add a separate provider with an appropriate interval.

## Glossary

- Agent: Coordinates models, active project/chat context, and attached workers/consumers.
- Consumer: A background process consuming a Messenger transport queue (async or summary).
- Worker: A task attached to the running session that may need to update the TUI.
- Timer Provider: A class that registers periodic callbacks with the Scheduler during boot.
- Scheduler: Lightweight scheduler that runs due timers and sleeps between them.
- LoopRunner: Facade that boots providers, ticks the scheduler, and sleeps.
- Application (TUI): Coordinates terminal events, layout, rendering, and user input.
