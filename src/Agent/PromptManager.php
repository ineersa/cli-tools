<?php

declare(strict_types=1);

namespace App\Agent;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class PromptManager
{
    private string $promptsDir = __DIR__.'/prompts';

    public function __construct()
    {
    }

    public function getSummarizerPrompt(int $maxTokens = 1000): string
    {
        $file = $this->promptsDir.'/summarizer.yaml';
        if (!is_file($file)) {
            throw new \RuntimeException(\sprintf('Summarizer prompt file not found at %s', $file));
        }

        try {
            $data = Yaml::parseFile($file);
        } catch (ParseException $e) {
            throw new \RuntimeException('Failed to parse summarizer.yaml: '.$e->getMessage(), previous: $e);
        }

        if (!\is_array($data) || !isset($data['system_prompt'])) {
            throw new \RuntimeException('summarizer.yaml is missing required "system_prompt" key');
        }

        $template = (string) $data['system_prompt'];

        $tokens = $maxTokens > 0 ? $maxTokens : (int) ($data['max_tokens'] ?? 1000);

        $prompt = strtr($template, [
            '{{max_tokens}}' => (string) $tokens,
        ]);

        return trim($prompt)."\n";
    }
}
