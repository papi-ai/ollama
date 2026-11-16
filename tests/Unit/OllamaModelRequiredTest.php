<?php

/*
 * This file is part of PapiAI,
 * A simple but powerful PHP library for building AI agents.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use PapiAI\Core\Exception\ProviderException;
use PapiAI\Core\Message;
use PapiAI\Ollama\OllamaProvider;

/**
 * Captures the request so the model resolution can be asserted without HTTP.
 */
class TestableOllamaModelProvider extends OllamaProvider
{
    public array $lastPayload = [];

    protected function request(array $payload): array
    {
        $this->lastPayload = $payload;

        return ['message' => ['role' => 'assistant', 'content' => 'ok'], 'done' => true];
    }
}

describe('OllamaProvider model selection', function () {
    it('throws before any HTTP call when no model was configured or passed', function () {
        $provider = new TestableOllamaModelProvider();

        expect(fn () => $provider->chat([Message::user('hi')]))
            ->toThrow(ProviderException::class, 'ollama list');

        expect($provider->lastPayload)->toBe([]);
    });

    it('uses the model passed per call', function () {
        $provider = new TestableOllamaModelProvider();

        $provider->chat([Message::user('hi')], ['model' => 'qwen3']);

        expect($provider->lastPayload['model'])->toBe('qwen3');
    });

    it('uses the configured default when the call names none', function () {
        $provider = new TestableOllamaModelProvider(defaultModel: 'llama4');

        $provider->chat([Message::user('hi')]);

        expect($provider->lastPayload['model'])->toBe('llama4');
    });
});
