# Project and Chat Workflow

This document explains how Projects and Chats are organized and how they interact inside the CLI/TUI application. It complements docs/architecture.md (runtime/loop) and docs/tui.md (UI details).

## Concepts at a Glance

- Project
  - A named workspace that holds:
    - Working directory (where commands operate, files are read/written, etc.).
    - Paths to custom instructions and settings (per-project guidance and configuration).
    - Default flag: one project can be marked as default and is opened automatically on app start.
  - You must create (and select) a Project before chatting. The app enforces this.

- Chat
  - A conversation scoped to a Project and a Mode (e.g., chat/plan/execution).
  - No chat is open by default. The first user message for a given Project/Mode creates and opens a chat automatically.
  - Previous chats can be listed and restored (re-opened) so you can continue where you left off.

- Summary
  - Automatically generated as conversations grow.
  - Used to compact older context so long threads remain within model limits.
  - Stored alongside chat history and reused when resuming/restoring chats.

## Lifecycle and Flows

### 1) First Run / Starting the App

1. Launch the TUI via `bin/console ai:client`.
2. Background consumers are started (async, summary). See docs/architecture.md for details.
3. The UI shows tips. No chat is open yet.
4. You must have a Project selected before sending non-command input. If none is selected, the TUI will ask you to specify a Project first.

### 2) Creating and Selecting a Project

- Create a new Project with your desired working directory and optional instruction/settings paths.
- Mark a Project as default to have it auto-selected next time.
- Switching the current Project updates the context for all subsequent actions (commands, chat messages).

Project commands (exact, from Autocomplete):
- `/project list` — list projects.
- `/project delete #` — delete project by id (e.g., `/project delete 3`).
- `/project create` — create new project (guided flow or prompts if supported).
- `/project edit #` — edit project by id.
- `/project change #` — change current project to project by id.

Internally, the Agent loads the default Project on startup. You can change it at any time using project commands. The Application prevents question submission until a Project is set.

### 3) Starting a Chat

- A Chat is created lazily: when you submit the first non-command message for the current Project/Mode, the system opens or creates an "open chat" and appends your message.
- Open chat is stored and can be resumed later.
- On application shutdown or explicit cleanup, the open chat state is reset to avoid stale sessions on next startup.

Indicative user flow:
1. Ensure a Project is selected (use `/project list` to find ids, `/project change #` to switch, or `/project create` to add one).
2. Type your message.
3. Press Ctrl+D to submit. The UI appends a “You” card and dispatches the question event to be processed.
4. Handlers and background consumers produce responses; the UI appends assistant/command cards accordingly.

### 4) Restoring and Managing Chats

Use chat commands to manage history (exact, from Autocomplete):
- `/chat list` — show list of chats.
- `/chat delete #` — delete chat by id.
- `/chat restore #` — restore chat by id (restores context and summary).
- `/chat summary #` — show chat summary by id.
- `/chat clear-all` — delete all chats; to start a new chat session, you can also use `/clear`.

Refer to `/help` for the exact set and syntax supported by your build.

## Automatic Summaries

- Why: Long conversations can exceed model context limits. Summaries compact earlier turns into a concise form while preserving key details and decisions.
- How: A background summary pipeline runs via a dedicated Messenger transport/consumer (see `summary` queue in config). As the chat grows, it computes or updates the summary of prior context.
- Usage: When appending new turns or restoring a chat, the summary is included to keep input within limits while retaining continuity.
- Storage: Summaries are stored along with chat turns/metadata and reused across sessions.

## Command Cheatsheet (exact, from Autocomplete)

- General
  - `/help` — show available commands and usage hints.
  - `/tools` — check available tools.
  - `/copy` — copy the last result to clipboard.
  - `/clear` — clear the screen and history; starts a new chat.
  - `/exit` — quit the application.

- Project
  - `/project list`
  - `/project delete #`
  - `/project create`
  - `/project edit #`
  - `/project change #`

- Chat
  - `/chat list`
  - `/chat delete #`
  - `/chat restore #`
  - `/chat summary #`
  - `/chat clear-all`

Note: Slash commands must be typed in the input box. Press Ctrl+D to execute a command (or to send a message when not starting with `/`).

## Under the Hood (brief)

- Project enforcement:
  - `Application::listenTerminalEvents()` rejects question submission if no Project is set (throws a problem that is surfaced in the TUI), ensuring all chats are anchored to a Project.
- Chat creation/opening:
  - `Agent` coordinates with `ChatService` to retrieve or create the open chat for the current Project/Mode on first message.
  - `Agent::cleanUpChat()` is called on startup/shutdown to reset stale open-chat state.
- Summary generation:
  - Configured in `config/packages/messenger.yaml` with a dedicated `summary` transport.
  - `ConsumerSummaryWorker` process consumes the `summary` queue.

For more details, see:
- docs/architecture.md — loop, timers, consumers/workers, messaging.
- docs/tui.md — components, events, and rendering.
