<?php

declare(strict_types=1);

namespace App\Llm;

use Symfony\Component\OptionsResolver\OptionsResolver;

final class LlmClientFactory
{
    public static function create(array $config): LlmClient
    {
        $r = new OptionsResolver();
        $r->define('api_base')->required()->allowedTypes('string');
        $r->define('api_key')->required()->allowedTypes('string');
        $r->define('model')->required()->allowedTypes('string');
        $r->define('max_input_tokens')->default(0)->allowedTypes('int');
        $r->define('max_output_tokens')->default(0)->allowedTypes('int');
        $r->define('max_tokens')->default(0)->allowedTypes('int');
        $r->define('supports')->default([])->allowedTypes('array');

        $o = $r->resolve($config);

        return new LlmClient(
            $o['api_base'],
            $o['api_key'],
            $o['model'],
            $o['max_input_tokens'],
            $o['max_output_tokens'],
            $o['max_tokens'],
            $o['supports'],
        );
    }
}
