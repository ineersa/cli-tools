<?php

declare(strict_types=1);

namespace App\Llm;

use OpenAI;
use OpenAI\Responses\Chat\CreateStreamedResponse;
use OpenAI\Responses\StreamResponse;

final readonly class LlmClient
{
    private readonly OpenAI\Client $openAIClient;

    public function __construct(
        private string $apiBase,
        private string $apiKey,
        private string $model,
        private int $maxInputTokens,
        private int $maxOutputTokens,
        private int $maxTokens,
        private array $supports,
    ) {
        $this->openAIClient = \OpenAI::factory()
            ->withApiKey($this->apiKey)
            ->withBaseUri($this->apiBase)
            ->make();
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

        return $this->openAIClient->chat()->create($parameters);
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

        return $this->openAIClient->chat()->createStreamed($parameters);
    }
}
