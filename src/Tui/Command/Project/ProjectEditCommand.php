<?php

namespace App\Tui\Command\Project;

use App\Entity\Project;
use App\Service\ProjectService;
use App\Tui\Application;
use App\Tui\Command\AbstractInteractionSessionCommand;
use App\Tui\Command\InteractionSessionInterface;
use App\Tui\Component\StepComponent;
use App\Tui\DTO\StepComponentDTO;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\FollowupException;
use App\Tui\Exception\ProblemException;
use App\Tui\State;
use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Style\Style;

final class ProjectEditCommand extends AbstractInteractionSessionCommand
{
    public function __construct(
        private State $state,
        private Application $application,
        private ProjectService $projectService,
    )
    {
        parent::__construct($this->state, $this->application);
    }

    public const COMMAND_TITLE = 'Edit project';

    public const ATTRIBUTES_AVAILABLE = ['name', 'workdir', 'is_default', 'instructions'];

    private string $attribute;

    private int $id = 0;

    private Project $projectEntity;

    public function step(string $line): void
    {
        $line = trim($line);

        if ($this->step === 1) {
            $this->attribute = strtolower($line);
            if (!in_array($this->attribute, self::ATTRIBUTES_AVAILABLE, true)) {
                throw new ProblemException('Attribute does not exist. Available: ' . implode(', ', self::ATTRIBUTES_AVAILABLE));
            }
            $this->step = 2;
            switch ($this->attribute) {
                case 'name':
                    $dto = new StepComponentDTO(
                        title: self::COMMAND_TITLE,
                        question: 'Enter project name',
                        borderStyle: Style::default()->fg(AnsiColor::LightYellow),
                        hint: 'Name must be ≥2 chars, letters/digits/dashes.',
                        progress: '2/2',
                    );
                    $this->addStepComponent($dto);
                    $this->application->clearInput();
                    throw new FollowupException();
                case 'workdir':
                    $dto = new StepComponentDTO(
                        title: self::COMMAND_TITLE,
                        question: 'Working directory (absolute path):',
                        borderStyle: Style::default()->fg(AnsiColor::LightYellow),
                        hint: 'Must exist & be writable',
                        progress: '2/2',
                    );
                    $this->addStepComponent($dto);
                    $this->application->clearInput();
                    throw new FollowupException();
                case 'is_default':
                    $dto = new StepComponentDTO(
                        title: self::COMMAND_TITLE,
                        question: 'Set as default? (y/N):',
                        borderStyle: Style::default()->fg(AnsiColor::LightYellow),
                        progress: '2/2',
                    );
                    $this->addStepComponent($dto);
                    $this->application->clearInput();
                    throw new FollowupException();
                case 'instructions':
                    $dto = new StepComponentDTO(
                        title: self::COMMAND_TITLE,
                        question: 'Instructions file (relative to workdir):',
                        borderStyle: Style::default()->fg(AnsiColor::LightYellow),
                        hint: 'default is AGENTS.md',
                        progress: '2/2',
                    );
                    $this->application->clearInput();
                    $this->addStepComponent($dto);
                    throw new FollowupException();
            }
        }
        if ($this->step === 2) {
            switch ($this->attribute) {
                case 'name':
                    if (!preg_match('/^[a-z0-9\-]{2,}$/i', $line)) {
                        throw new ProblemException('Name must be ≥2 chars, letters/digits/dashes.');
                    }
                    if ($this->projectService->projectRepository->findOneBy(['name' => $line])) {
                        throw new ProblemException('Project with this name already exists.');
                    }
                    $this->projectEntity->setName($line);
                    $this->projectService->update($this->projectEntity);
                    throw new CompleteException("/project edit \n Project #" . $this->projectEntity->getId() . " was successfully updated.");
                case 'workdir':
                    if (!is_dir($line)) {
                        throw new ProblemException('Directory not found.');
                    }
                    $this->projectEntity->setWorkdir(rtrim($line, '/'));
                    $this->projectService->update($this->projectEntity);
                    throw new CompleteException("/project edit \n Project #" . $this->projectEntity->getId() . " was successfully updated.");
                case 'is_default':
                    $this->projectEntity->setIsDefault(strtolower($line) === 'y');
                    $this->projectService->update($this->projectEntity);
                    throw new CompleteException("/project edit \n Project #" . $this->projectEntity->getId() . " was successfully updated.");
                case 'instructions':
                    $this->projectEntity->setInstructions($line);
                    $this->projectService->update($this->projectEntity);
                    throw new CompleteException("/project edit \n Project #" . $this->projectEntity->getId() . " was successfully updated.");
            }
        }
    }

    public function sendInitialMessage(): never
    {
        $projectEntity = $this->projectService->projectRepository
            ->find($this->getId());
        if (!$projectEntity) {
            throw new ProblemException('Project not found.');
        }
        $this->projectEntity = $projectEntity;

        $this->state->setInteractionSession($this);

        $dto = new StepComponentDTO(
            title: self::COMMAND_TITLE,
            question: 'Enter attribute you want to edit',
            borderStyle: Style::default()->fg(AnsiColor::LightYellow),
            hint: 'Available: ' . implode(', ', self::ATTRIBUTES_AVAILABLE),
            progress: '1/2',
        );
        $this->addStepComponent($dto);
        $this->application->clearInput();
        throw new FollowupException();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

}
