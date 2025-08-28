<?php

namespace App\Tui\Component;

use App\Tui\State;
use App\Tui\Utility\InputUtilities;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\Terminal;
use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Extension\Core\Widget\Block\Padding;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\List\ListItem;
use PhpTui\Tui\Extension\Core\Widget\List\ListState;
use PhpTui\Tui\Extension\Core\Widget\ListWidget;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Text\Title;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Widget;

class AutocompleteComponent implements Component
{
    public const COMMANDS = [
        ['name' => '/help',    'desc' => 'show help and available commands'],
        ['name' => '/chat',    'desc' => 'manage conversation history', 'followup' => [
            'list'            => 'show list of chats',
            'delete #'    => 'delete chat #123',
            'restore #'   => 'restore chat #123, will use *COMPACT* version',
            'restore-full #' => 'restore chat #123, will use *FULL* version',
            'clear'           => 'delete all chats',
        ]],
        ['name' => '/compact', 'desc' => 'compact current chat'],
        ['name' => '/clear',   'desc' => 'clear the screen and history, starts new chat'],
        ['name' => '/copy',    'desc' => 'copy the last result to clipboard'],
        ['name' => '/tools',   'desc' => 'check available tools'],
        ['name' => '/exit',    'desc' => 'quit'],
    ];

    public const MAX_ROWS_VISIBLE = 5;

    private ListState $acState;

    /** @var list<array{name:string, desc:string}> */
    private array $acFiltered = [];

    /** Title that adapts between top-level and follow-up mode */
    private string $acTitle = 'Commands, start typing from / · Enter or Tab to accept';

    public function __construct(
        private State $state,
        private Terminal $terminal,
    ) {
        $this->acState = new ListState(offset: 0, selected: null);
    }

    public function build(): Widget
    {
        $items = [];
        foreach ($this->acFiltered as $cmd) {
            $items[] = new ListItem(
                Text::fromString($cmd['name'].'  '.$cmd['desc']),
                Style::default()
            );
        }

        return BlockWidget::default()
            ->borders(Borders::NONE)
            ->titles(Title::fromString($this->acTitle))
            ->titleStyle(Style::default()->fg(AnsiColor::DarkGray))
            ->padding(Padding::horizontal(1))
            ->widget(
                ListWidget::default()
                    ->items(...$items)
                    ->state($this->acState)
                    ->highlightStyle(Style::default()->fg(AnsiColor::LightCyan))
                    ->highlightSymbol("·> ")
            );
    }

    public function handle(Event $event): void
    {
        if ($event instanceof CharKeyEvent) {
            // Tab may arrive as literal "\t"
            if ($event->char === "\t" && $this->state->isAcOpen()) {
                $this->acceptAutocomplete();
                return;
            }
            if ($this->state->isEditing()) {
                // Accept multi-char bursts (paste), keep ASCII & newlines
                $chunk = InputUtilities::sanitizePaste($event->char);
                if ($chunk !== '') {
                    $this->recomputeAutocomplete(resetCursor: true);
                }
            }
        } elseif ($event instanceof CodedKeyEvent) {
            $code = $event->code;

            if ($this->state->isEditing()) {
                // When autocomplete is open, intercept Up/Down/Enter/Tab
                if ($this->state->isAcOpen() && $code === KeyCode::Up) {
                    $this->acMoveSelection(-1);
                    return;
                }
                if ($this->state->isAcOpen() && $code === KeyCode::Down) {
                    $this->acMoveSelection(1);
                    return;
                }
                if ($this->state->isAcOpen() && ($code === KeyCode::Enter || $code === KeyCode::Tab)) {
                    $this->acceptAutocomplete();
                    return;
                }

                if ($code === KeyCode::Esc && str_starts_with($this->state->getInput(), '/')) {
                    // Close AC on ESC
                    $this->state->setInput('');
                    $this->state->setCharIndex(0);
                    $this->state->setStickyCol(0);
                    $this->state->setScrollTopLine(0);
                    $this->recomputeAutocomplete(resetCursor: true);
                }

                if ($code === KeyCode::Backspace) {
                    $this->recomputeAutocomplete(resetCursor: true);
                }
            }
        }
    }

    public function recomputeAutocomplete(bool $resetCursor): void
    {
        $input = $this->state->getInput();

        if (!str_starts_with($input, '/')) {
            $this->closeAc();
            return;
        }

        // Parse: "/chat foo" => $base="/chat", $tail="foo"
        [$baseCmd, $tail] = $this->parseSlashCommand($input);

        // CASE 1: No base match yet -> show top-level commands filtered by entire query after '/'
        $matched = $this->findCommandByName($baseCmd);
        if ($matched === null) {
            $this->acTitle = 'Commands · Enter/Tab to accept';
            $this->filterTopLevel($input);
            $this->state->setAcOpen(count($this->acFiltered) > 0);
            $this->syncCursorAfterRecompute($resetCursor);
            return;
        }

        // CASE 2: We have a base match and (optional) follow-ups
        if (isset($matched['followup']) && \is_array($matched['followup'])) {
            $this->acTitle = sprintf('Follow-ups for %s · Enter/Tab to accept', $matched['name']);
            $this->filterFollowups($matched['name'], $matched['followup'], $tail);
            $this->state->setAcOpen(count($this->acFiltered) > 0);
            $this->syncCursorAfterRecompute($resetCursor);
            return;
        }

        // CASE 3: Base has no follow-ups -> keep showing other commands if user is still typing (fallback)
        $this->acTitle = 'Commands · Enter/Tab to accept';
        $this->filterTopLevel($input);
        $this->state->setAcOpen(count($this->acFiltered) > 0);
        $this->syncCursorAfterRecompute($resetCursor);
    }

    /** FULL ACCEPT: replace input with selected item + space (works for commands and follow-ups) */
    private function acceptAutocomplete(): void
    {
        if (!$this->state->isAcOpen() || $this->acState->selected === null) return;
        $sel = $this->acFiltered[$this->acState->selected]['name'] ?? '';
        if ($sel === '') return;


        $this->state->setInput(str_ends_with($sel, '#') ? $sel : $sel.' ');
        $this->state->setCharIndex(\mb_strlen($this->state->getInput()));
        $this->state->setScrollTopLine(0);

        // Recompute so that, if there are deeper follow-ups in the future, they’d show;
        // for now, we just keep AC visible if still applicable.
        $this->recomputeAutocomplete(false);

        InputUtilities::ensureCaretVisible($this->state, $this->terminal);
        InputUtilities::updateStickyFromIndex($this->state);
    }

    // -----------------------
    // Internals
    // -----------------------

    private function closeAc(): void
    {
        $this->state->setAcOpen(false);
        $this->acFiltered = [];
        $this->acState->selected = null;
        $this->acState->offset = 0;
        $this->acTitle = 'Commands, start typing from / · Enter or Tab to accept';
    }

    private function acMoveSelection(int $delta): void
    {
        if (!$this->state->isAcOpen() || $this->acState->selected === null) return;

        $count = count($this->acFiltered);
        $this->acState->selected = max(0, min($count - 1, $this->acState->selected + $delta));
        $this->acClampOffset();
    }

    private function acClampOffset(): void
    {
        if ($this->acState->selected === null) {
            $this->acState->offset = 0;
            return;
        }
        $sel = $this->acState->selected;
        $off = $this->acState->offset;

        if ($sel < $off) {
            $off = $sel;
        } elseif ($sel >= $off + self::MAX_ROWS_VISIBLE) {
            $off = $sel - self::MAX_ROWS_VISIBLE + 1;
        }
        $maxOff = max(0, count($this->acFiltered) - self::MAX_ROWS_VISIBLE);
        $this->acState->offset = max(0, min($off, $maxOff));
    }

    private function syncCursorAfterRecompute(bool $resetCursor): void
    {
        if ($this->state->isAcOpen()) {
            if ($resetCursor || $this->acState->selected === null) {
                $this->acState->selected = 0;
                $this->acState->offset = 0;
            } else {
                $this->acState->selected = min($this->acState->selected, count($this->acFiltered) - 1);
                $this->acClampOffset();
            }
        } else {
            $this->acState->selected = null;
            $this->acState->offset = 0;
        }
    }

    /**
     * Parses "/chat foo bar" -> ['/chat', 'foo']
     * If only "/cha" -> ['/cha', '']
     */
    private function parseSlashCommand(string $input): array
    {
        $raw = ltrim($input, '/');
        if ($raw === '') {
            return ['/', ''];
        }
        $spacePos = mb_strpos($raw, ' ');
        if ($spacePos === false) {
            // Typing the base: "/cha"
            return ['/'.trim($raw), ''];
        }
        $base = '/'.trim(mb_substr($raw, 0, $spacePos));
        $tail = trim(explode(" ", mb_substr($raw, $spacePos + 1))[0] ?? '');
        return [$base, $tail];
    }

    /**
     * Finds exact command by its "/name" (case-insensitive)
     */
    private function findCommandByName(string $base): ?array
    {
        $needle = mb_strtolower($base);
        foreach (self::COMMANDS as $c) {
            if (mb_strtolower($c['name']) === $needle) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Filters top-level COMMANDS by whatever the user has after '/'
     */
    private function filterTopLevel(string $input): void
    {
        $q = rtrim(substr($input, 1)); // after '/'
        $qLower = mb_strtolower($q);

        $filtered = [];
        foreach (self::COMMANDS as $c) {
            $inName = $q === '' || str_contains(mb_strtolower($c['name']), $qLower);
            $inDesc = $q === '' || str_contains(mb_strtolower($c['desc']), $qLower);
            if ($inName || $inDesc) {
                $filtered[] = $c;
            }
        }
        $this->acFiltered = $filtered;
    }

    /**
     * Builds follow-up choices like "/chat list", "/chat clear", … and filters by $tail
     *
     * @param array<string,string> $followups
     */
    private function filterFollowups(string $baseName, array $followups, string $tail): void
    {
        $tailLower = mb_strtolower($tail);
        $out = [];
        foreach ($followups as $fName => $desc) {
            $full = trim($baseName.' '.$fName);
            if ($tail === '' ||
                str_contains(mb_strtolower($fName), $tailLower) ||
                str_contains(mb_strtolower($desc), $tailLower)
            ) {
                $out[] = ['name' => $full, 'desc' => $desc];
            }
        }
        $this->acFiltered = $out;
    }
}
