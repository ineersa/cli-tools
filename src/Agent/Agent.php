<?php

declare(strict_types=1);

namespace App\Agent;

use LLPhant\Chat\OpenAIChat;
use LLPhant\OpenAIConfig;

class Agent
{
    private Mode $mode;
    private OpenAIChat $chat;

    /**
     * @throws \Exception
     */
    public function __construct(
        private string $model,
    ) {
        $this->mode = Mode::getDefaultMode();
        $config = new OpenAIConfig();
        $config->apiKey = '-';
        $config->url = 'http://localhost:11434/v1';
        $config->model = $this->model;
        $this->chat = new OpenAIChat($config);
    }

    public function getMode(): Mode
    {
        return $this->mode;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setMode(Mode $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getChat(): OpenAIChat
    {
        return $this->chat;
    }
}
