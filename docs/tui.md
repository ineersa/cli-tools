# TUI Architecture

This document explains how the Terminal User Interface (TUI) is structured and rendered in this project. It covers the core Application, available UI components, the event flow, and the rendering lifecycle. Use this as a companion to docs/architecture.md, which describes the overall loop and timers.

## Overview

- Entry point into TUI: `App\Command\AiClientCommand` switches terminal to alternate screen, enables raw mode, and boots the loop.
- UI refresh is driven by a periodic timer provider: `App\Tui\Loop\TimerProviders\UiTimerProvider` (~16ms, ~60 FPS).
- The orchestrator is `App\Tui\Application`, which:
  - Listens for terminal events and updates state.
  - Builds the current layout (`Application::layout()`), composed of TUI components.
  - Delegates rendering to PhpTui Display in `UiTimerProvider`.
  - Calculates and positions the caret (cursor) in a multi-line input box.

## Core Classes

- `App\Tui\Application`
  - Constructor wires `Layout` with concrete components (see Components).
  - Pushes startup content (logo and tips) into the content stream.
  - Methods:
    - `listenTerminalEvents()`: polls terminal events, handles submissions and commands, dispatches `QuestionReceivedEvent`, and forwards events to components.
    - `layout()`: computes sizes and returns the top-level Grid widget containing component widgets.
    - `clearInput()`: clears the input and recomputes autocomplete where appropriate.

- `App\Tui\Loop\TimerProviders\UiTimerProvider`
  - Registers a periodic callback that:
    - Calls `Application::listenTerminalEvents()`.
    - Draws `Application::layout()` with PhpTui `Display`.
    - Calculates caret position via `InputUtilities::wrapTextAndLocateCaret()` and moves the terminal cursor with `TerminalUtilities::moveCursorToInputBox()`.

- `App\Tui\State`
  - Central state for the TUI: input buffer, caret index, scroll, content items, dynamic island components, interaction session, project/mode, etc. (See code for full details.)
  - Exposes `isRequireReDrawing()` flag so components or subsystems can trigger a redraw without a terminal event.

## Components

Components are instantiated inside `Application` via `Layout` and rendered into a vertical grid. Each component implements a `build()` method that returns a PhpTui Widget, and a `handle($event)` method to react to terminal events.

Currently present components (see `App\Tui\Component\*`):

- `WindowedContentComponent`
  - Shows the scrolling history (content pane). Height is fixed (see `CONTENT_HEIGHT`); contains cards/items pushed to the state, e.g., logo, tips, user/assistant cards, command result cards.

- `AutocompleteComponent`
  - Shows inline or panel-based command suggestions/completions based on the current input. Recomputed frequently (after edits or when input cleared).

- `StatusComponent`
  - Bottom or top status line with current mode/model/project, and potentially diagnostics.

- `HelpStringComponent`
  - Shows short help for key bindings, such as Ctrl+D to submit and Ctrl+C to exit.

- `DynamicIslandComponent`
  - Transient, overlay-like area for surfacing problems or important notices (e.g., consumer/worker restarts). Populated with `ProblemComponent` and other ad-hoc components when exceptions or contextual UI flows are active.
  - Constraint-aware: Dynamic Island renders a vertical grid of components that implement `ConstraintAwareComponent`. Each child provides its own layout `constraint()` (min/percentage/etc.), and the island forwards those constraints to its internal Grid. See `App\Tui\Component\ConstraintAwareComponent` and examples like `ProblemComponent`, `TableListComponent`, `ProgressComponent`, and `StepComponent`. 

- `InputComponent`
  - Multiline input box. Handles editing (insert text, move caret, scroll). Integrates with `InputUtilities` to manage wrapping and visibility.

- `ProblemComponent`
  - A component used inside `DynamicIslandComponent` to display problem messages arising from operations (e.g., when workers/consumers are restarted or commands fail with `ProblemException`).

- `TextContentComponentItems`
  - Factory-like provider of predefined content items (e.g., logo, tips, contextual help) inserted into the content pane at startup. Provides helpers like `getLogo()`, `getTips()`, and `getHelp()` returning styled `ContentItem`s.

- `ContentItemFactory`
  - Factory used throughout the TUI to generate styled “cards” (`ContentItem` instances) added to the scrolling content pane. Known types:
    - `EMPTY_ITEM` – empty spacer item.
    - `USER_CARD` – shows user input; bordered card with "You" title and teal border.
    - `RESPONSE_CARD` – assistant response; bordered with yellow border/title and keeps `originalString` for raw content.
    - `COMMAND_CARD` – results/info produced by commands or completed sessions; green text, bordered.
  - Used by `Application` when submitting input (Ctrl+D) to append user messages, and when commands/sessions complete to append result cards.

- Utility components (used primarily inside Dynamic Island or content cards):
  - `TableListComponent`: Interactive table list with keyboard navigation (Up/Down/Home/End, Esc to dismiss). Implements `ConstraintAwareComponent` to request a minimum height and auto-computes column width percentages based on content length.
  - `ProgressComponent`: Displays an in-UI progress indicator for long-running tasks; constraint-aware so it fits the island without taking excessive space.
  - `StepComponent`: Renders a list of steps or a wizard-like flow, updating as the task progresses; also implements `ConstraintAwareComponent`.

Note: Components cooperate via `App\Tui\State` rather than talking to each other directly. Application collects all `getComponents()` from `Layout` and forwards events to each via `component->handle($event)`.

## Events and Interactions

- Terminal events (from `PhpTui\Term`):
  - `CharKeyEvent`: character input (includes modifiers).
  - `CodedKeyEvent`: special keys (arrows, function keys). The codebase currently stubs this section for future handling; individual components may handle coded keys within their `handle()` methods.

- Global hotkeys handled in `Application::listenTerminalEvents()`:
  - Ctrl+C (Char `c` with `KeyModifiers::CONTROL`): raises `UserInterruptException` to exit the application gracefully.
  - Ctrl+D (Char `d` with `KeyModifiers::CONTROL`): submit behavior:
    - If input is empty or whitespace, ignore.
    - If an interaction session is active in state, it processes a step; exceptions are translated to UI via `ProblemComponent`, or may indicate follow-up/complete flows.
    - If input starts with `/`, it is treated as a TUI command and routed to `App\Tui\Command\Runner`. Results/errors are shown as cards or dynamic island messages.
    - Otherwise, validates project presence, appends a user card to content, clears input, and dispatches `App\Events\QuestionReceivedEvent` with a generated requestId and the submitted text.

- Component event handling loop:
  - After global handling, every polled event is sent to each component: `foreach ($layout->getComponents() as $component) { $component->handle($event); }`.
  - This allows components to respond to keystrokes, scrolling, selection changes, etc.

- Redraw triggers:
  - The event loop continues while there are incoming terminal events OR when `State::isRequireReDrawing()` is set (used to trigger UI updates unrelated to keypresses).

## Rendering Lifecycle

1. UiTimerProvider’s periodic callback runs (~every 16 ms).
2. `Application::listenTerminalEvents()` drains and processes terminal events and redraw requests, updating `State`.
3. `Application::layout()` builds a `GridWidget` with vertical `Direction::Vertical`, computing:
   - Wrapped input and visible input height based on current caret position and terminal inner width.
   - Constraints from `Layout` using the computed input height and the fixed `WindowedContentComponent::CONTENT_HEIGHT`.
   - Component widgets via `component->build()`.
4. The PhpTui `Display` draws the layout to the terminal backend (PhpTerm).
5. Caret placement:
   - `InputUtilities::wrapTextAndLocateCaret()` returns wrapped lines and caret line/column relative to input box.
   - `TerminalUtilities::moveCursorToInputBox()` sets the cursor position, adjusted for `State::getScrollTopLine()`.
   - Cursor is explicitly shown (`Actions::cursorShow()`).

## Commands and Messages

- Slash commands: lines starting with `/` are executed via `App\Tui\Command\Runner`. The exact set and syntax come from the TUI autocomplete (see `AutocompleteComponent::COMMANDS`), e.g., /help, /project (list/delete#/create/edit#/change#), /chat (list/delete#/restore#/summary#/clear-all), /tools, /copy, /clear, /exit.
- User questions: emitted as `QuestionReceivedEvent` and typically routed to Messenger handlers, ultimately yielding messages like `App\Message\AssistantResponseReceived` processed by handlers that update TUI state/content.
- Background health and progress:
  - `WorkerPollTimerProvider` (250 ms) calls `Agent::pollWorkers()`.
  - `ConsumerPollTimerProvider` (1000 ms) calls `Agent::pollConsumers()`.
  - `ProblemException`s are caught and shown via `DynamicIslandComponent` -> `ProblemComponent`.

## Extensibility

- Adding a new component:
  - Implement a `Component` (see existing implementations for contract) that exposes `build()` and `handle()`.
  - Register it inside `Layout` (or via a future composition mechanism) to make it part of `Application::layout()`.

- Handling new keys or interactions:
  - Extend `Application::listenTerminalEvents()` for global shortcuts.
  - Or handle key events inside the specific component’s `handle()` method to keep concerns localized.

- Reactive redraws:
  - When a background event changes state (e.g., a message handler updates state), set `State::setRequireReDrawing(true)` to have the UI timer pick up a render even without new key events.

## Rendering Stack Details

- Rendering library: PhpTui
  - Backend: `PhpTui\Tui\Bridge\PhpTerm\PhpTermBackend` with `PhpTui\Term\Terminal`.
  - Extensions: `BdfExtension` is enabled for bitmap fonts.
  - Top-level widget: `GridWidget` with `Direction::Vertical`.

## Glossary

- Application: Coordinates events, state, and layout for the TUI.
- Component: A UI building block with `build()` (Widget) and `handle()` (events).
- Dynamic Island: Transient notification area used to show problems or status.
- Display: PhpTui object responsible for drawing widgets to the terminal backend.
- State: Centralized model of UI data (input, caret, contents, selections, etc.).
- UI Timer: Periodic task that pulls events, renders, and positions the cursor.
- ConstraintAwareComponent: Component contract that exposes a `constraint()` returning a PhpTui layout Constraint; used by Dynamic Island children to control their own sizing.
