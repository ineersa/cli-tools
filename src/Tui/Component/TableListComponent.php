<?php

declare(strict_types=1);

namespace App\Tui\Component;

use App\Tui\State;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Extension\Core\Widget\Table\TableCell;
use PhpTui\Tui\Extension\Core\Widget\Table\TableRow;
use PhpTui\Tui\Extension\Core\Widget\Table\TableState;
use PhpTui\Tui\Extension\Core\Widget\TableWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Widget\Widget;

class TableListComponent implements Component, ConstraintAwareComponent
{
    public const NAME = 'tableList';
    private TableState $tableState;
    private int $selected = 0;

    /**
     * @param non-empty-array<string, mixed> $data
     */
    public function __construct(
        private State $state,
        private array $data,
    ) {
        $this->tableState = new TableState();
    }

    public function build(): Widget
    {
        $headerRow = TableRow::fromCells(
            ...array_map(function (string $key) {
                return TableCell::fromString($key);
            }, array_keys($this->data[0]))
        );
        $headerRow->style = Style::default()->fg(AnsiColor::LightYellow);

        return TableWidget::default()
            ->highlightStyle(Style::default()->black()->onCyan())
            ->state($this->tableState)
            ->select($this->selected)
            ->highlightSymbol('X')
            ->widths(...$this->calculatePercentage())
            ->header(
                $headerRow
            )
            ->rows(...array_map(function (array $data) {
                return TableRow::fromCells(
                    ...array_map(function (mixed $value) {
                        return TableCell::fromString((string)$value);
                    }, $data)
                );
            }, $this->data));
    }

    public function handle(Event $event): void
    {
        if ($event instanceof CodedKeyEvent && KeyCode::Esc === $event->code) {
            $components = $this->state->getDynamicIslandComponents();
            unset($components[self::NAME]);
            $this->state->setDynamicIslandComponents($components);
            $this->state->setEditing(true);
        }
        if ($event instanceof CodedKeyEvent) {
            if (KeyCode::Down === $event->code) {
                if ($this->selected < \count($this->data) - 1) {
                    ++$this->selected;
                }
            }
            if (KeyCode::Up === $event->code) {
                if ($this->selected > 0) {
                    --$this->selected;
                }
            }
            if (KeyCode::Home === $event->code) {
                $this->selected = 0;
            }
            if (KeyCode::End === $event->code) {
                $this->selected = \count($this->data) - 1;
            }
        }
    }

    public function constraint(): Constraint
    {
        return Constraint::min(3);
    }

    /**
     * @return Constraint\PercentageConstraint[]
     */
    private function calculatePercentage(): array
    {
        $longestByField = [];
        $longestLine = 0;
        foreach ($this->data as $row) {
            $stringLength = 0;
            foreach ($row as $key => $value) {
                $length = mb_strlen((string) $value) + 5; // some buffer
                if (!isset($longestByField[$key])) {
                    $longestByField[$key] = $length;
                }
                if ($longestByField[$key] < $length) {
                    $longestByField[$key] = $length;
                }
                $stringLength += $length;
            }
            if ($stringLength > $longestLine) {
                $longestLine = $stringLength;
            }
        }
        if (0 === $longestLine) {
            return [];
        }
        $percentage = [];
        foreach ($longestByField as $value) {
            $percentage[] = Constraint::percentage((int)ceil($value * 100 / $longestLine));
        }

        return $percentage;
    }
}
