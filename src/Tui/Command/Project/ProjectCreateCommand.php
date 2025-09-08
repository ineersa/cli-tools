<?php

declare(strict_types=1);

namespace App\Tui\Command\Project;

use App\Service\ProjectService;
use App\Tui\Application;
use App\Tui\Command\AbstractInteractionSessionCommand;
use App\Tui\DTO\StepComponentDTO;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\FollowupException;
use App\Tui\Exception\ProblemException;
use App\Tui\State;
use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Style\Style;

final class ProjectCreateCommand extends AbstractInteractionSessionCommand
{
    public const COMMAND_TITLE = 'Create project';
    /**
     * @var array{name?: string|null, workdir?: string|null, is_default?: bool, instructions?: string}
     */
    private array $data = [];

    public function __construct(
        private State $state,
        private Application $application,
        private ProjectService $projectService,
    ) {
        parent::__construct($this->state);
    }

    public function step(string $line): never
    {
        $line = trim($line);

        switch ($this->step) {
            case 1: // name
                if (!preg_match('/^[a-z0-9\-]{2,}$/i', $line)) {
                    throw new ProblemException('Name must be ≥2 chars, letters/digits/dashes.');
                }
                if ($this->projectService->projectRepository->findOneBy(['name' => $line])) {
                    throw new ProblemException('Project with this name already exists.');
                }
                $this->data['name'] = $line;
                $this->step = 2;
                $dto = new StepComponentDTO(
                    title: self::COMMAND_TITLE,
                    question: 'Working directory (absolute path):',
                    borderStyle: Style::default()->fg(AnsiColor::LightYellow),
                    hint: 'Must exist & be writable',
                    progress: '2/5',
                );
                $this->addStepComponent($dto);
                $this->application->clearInput();
                throw new FollowupException();
            case 2: // workdir
                if (!is_dir($line)) {
                    throw new ProblemException('Directory not found.');
                }
                $this->data['workdir'] = rtrim($line, '/');
                $this->step = 3;
                $dto = new StepComponentDTO(
                    title: self::COMMAND_TITLE,
                    question: 'Set as default? (y/N):',
                    borderStyle: Style::default()->fg(AnsiColor::LightYellow),
                    progress: '3/5',
                );
                $this->addStepComponent($dto);
                $this->application->clearInput();
                throw new FollowupException();
            case 3: // default
                $this->data['is_default'] = 'y' === strtolower($line);
                $this->step = 4;
                $dto = new StepComponentDTO(
                    title: self::COMMAND_TITLE,
                    question: 'Instructions file (relative to workdir):',
                    borderStyle: Style::default()->fg(AnsiColor::LightYellow),
                    hint: 'default is AGENTS.md',
                    progress: '4/5',
                );
                $this->application->clearInput();
                $this->addStepComponent($dto);
                throw new FollowupException();
            case 4: // instructions
                $this->data['instructions'] = $line;
                $this->step = 5;
                $dto = new StepComponentDTO(
                    title: self::COMMAND_TITLE,
                    question: 'Confirm save? (y/N):',
                    borderStyle: Style::default()->fg(AnsiColor::LightYellow),
                    hint: \sprintf(
                        'name=%s, workdir=%s, default=%s, instructions=%s',
                        $this->data['name'],
                        $this->data['workdir'],
                        $this->data['is_default'] ? 'yes' : 'no',
                        $this->data['instructions']
                    ),
                    progress: '5/5',
                );
                $this->addStepComponent($dto);
                $this->application->clearInput();
                throw new FollowupException();
            default: // commit
                if ('y' !== strtolower($line)) {
                    $this->cancel();
                    throw new ProblemException('Cancelled.');
                }
                try {
                    $project = $this->projectService->create($this->data);
                } catch (\Throwable $exception) {
                    throw new ProblemException($exception->getMessage());
                }
                $dto = new StepComponentDTO(
                    title: self::COMMAND_TITLE,
                    question: \sprintf('Project %s has been created', $this->data['name']),
                    borderStyle: Style::default()->fg(AnsiColor::LightGreen),
                );
                $this->addStepComponent($dto);
                $this->data = [
                    'name' => null,
                    'workdir' => null,
                    'is_default' => false,
                    'instructions' => 'AGENTS.md',
                ];
                $text = \sprintf("/project create \n Project #%s has been created", $project->getId());
                throw new CompleteException($text);
        }
    }

    public function cancel(): void
    {
        $this->state->setInteractionSession(null);
    }

    public function sendInitialMessage(): never
    {
        $this->state->setInteractionSession($this);

        $dto = new StepComponentDTO(
            title: self::COMMAND_TITLE,
            question: 'Enter project name',
            borderStyle: Style::default()->fg(AnsiColor::LightYellow),
            hint: 'Name must be ≥2 chars, letters/digits/dashes.',
            progress: '1/5',
        );
        $this->addStepComponent($dto);
        $this->application->clearInput();
        throw new FollowupException();
    }
}
