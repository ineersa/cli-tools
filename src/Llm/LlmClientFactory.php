<?php

declare(strict_types=1);

namespace App\Llm;

use Symfony\Component\OptionsResolver\OptionsResolver;

final class LlmClientFactory
{
    /**
     * @param array{api_base:string, api_key:string, model:string, max_input_tokens?:int, max_output_tokens?:int, max_tokens?:int, supports?: array<string, bool>} $config
     */
    public static function create(array $config): LlmClient
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver->define('api_base')->required()->allowedTypes('string');
        $optionsResolver->define('api_key')->required()->allowedTypes('string');
        $optionsResolver->define('model')->required()->allowedTypes('string');
        $optionsResolver->define('max_input_tokens')->default(0)->allowedTypes('int');
        $optionsResolver->define('max_output_tokens')->default(0)->allowedTypes('int');
        $optionsResolver->define('max_tokens')->default(0)->allowedTypes('int');
        $optionsResolver->define('supports')->default([])->allowedTypes('array');

        $option = $optionsResolver->resolve($config);

        return new LlmClient(
            $option['api_base'],
            $option['api_key'],
            $option['model'],
            $option['max_input_tokens'],
            $option['max_output_tokens'],
            $option['max_tokens'],
            $option['supports'],
        );
    }
}
