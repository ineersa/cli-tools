<?php

declare(strict_types=1);

namespace App\Llm;

use OpenAI;
use OpenAI\Contracts\ClientContract;
use OpenAI\Responses\Chat\CreateStreamedResponse;
use OpenAI\Responses\StreamResponse;

final class LlmClient
{
    private ?ClientContract $openAIClient = null;

    /**
     * @param array<string, bool> $supports
     */
    public function __construct(
        private string $apiBase,
        private string $apiKey,
        private string $model,
        private int $maxInputTokens,
        private int $maxOutputTokens,
        private int $maxTokens,
        private array $supports,
    ) {
    }

    protected function buildOpenAIClient(): ClientContract
    {
        return \OpenAI::factory()
            ->withApiKey($this->apiKey)
            ->withBaseUri($this->apiBase)
            ->make();
    }

    public function setOpenAIClient(ClientContract $openAIClient): void
    {
        $this->openAIClient = $openAIClient;
    }

    private function client(): ClientContract
    {
        if (null === $this->openAIClient) {
            $this->openAIClient = $this->buildOpenAIClient();
        }

        return $this->openAIClient;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getLimit(Limits $limitItem): int
    {
        return match ($limitItem) {
            Limits::MaxInputTokens => $this->maxInputTokens,
            Limits::MaxOutputTokens => $this->maxOutputTokens,
            Limits::MaxTokens => $this->maxTokens,
        };
    }

    // TODO add enum
    public function supports(string $feature): bool
    {
        return (bool) ($this->supports[$feature] ?? false);
    }

    /**
     * Creates a completion for the chat message.
     *
     * @see https://platform.openai.com/docs/api-reference/chat/create
     *
     * @param array<string, mixed> $parameters
     */
    public function completion(array $parameters): OpenAI\Responses\Chat\CreateResponse
    {
        $parameters['model'] = $this->model;

        return $this->client()->chat()->create($parameters);
    }

    /**
     * Creates a streamed completion for the chat message.
     *
     * @see https://platform.openai.com/docs/api-reference/chat/create
     *
     * @param array<string, mixed> $parameters
     *
     * @return StreamResponse<CreateStreamedResponse>
     */
    public function completionStreamed(array $parameters): StreamResponse
    {
        $parameters['model'] = $this->model;

        return $this->client()->chat()->createStreamed($parameters);
    }
}
