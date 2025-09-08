<?php

declare(strict_types=1);

namespace App\Tui\Loop\TimerProviders;

use App\Tui\Application;
use App\Tui\Loop\Scheduler;
use App\Tui\Loop\TimerProviderInterface;
use App\Tui\State;
use App\Tui\Utility\InputUtilities;
use App\Tui\Utility\TerminalUtilities;
use PhpTui\Term\Actions;
use PhpTui\Term\Terminal;
use PhpTui\Tui\Bridge\PhpTerm\PhpTermBackend as PhpTuiPhpTermBackend;
use PhpTui\Tui\DisplayBuilder;
use PhpTui\Tui\Extension\Bdf\BdfExtension;

final readonly class UiTimerProvider implements TimerProviderInterface
{
    public function __construct(
        private Terminal $terminal,
        private Application $app,
        private State $state,
    ) {
    }

    public function register(Scheduler $scheduler): void
    {
        $display = DisplayBuilder::default(PhpTuiPhpTermBackend::new($this->terminal))
            ->addExtension(new BdfExtension())
            ->build();

        // ~60fps (16ms)
        $scheduler->addPeriodic(16, function () use ($display): void {
            $this->app->listenTerminalEvents();

            $display->draw($this->app->layout());

            [, $caretLine, $caretCol] = InputUtilities::wrapTextAndLocateCaret(
                $this->state->getInput(),
                $this->state->getCharIndex(),
                TerminalUtilities::getTerminalInnerWidth($this->terminal)
            );
            TerminalUtilities::moveCursorToInputBox(
                $this->terminal,
                $caretLine,
                $caretCol,
                $this->state->getScrollTopLine()
            );
            $this->terminal->execute(Actions::cursorShow());
        });
    }
}
