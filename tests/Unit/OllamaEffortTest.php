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

use PapiAI\Core\Effort;
use PapiAI\Core\Message;
use PapiAI\Ollama\OllamaProvider;

/**
 * Captures the request payload so effort mapping can be asserted without HTTP.
 */
class TestableOllamaEffortProvider extends OllamaProvider
{
    public array $lastPayload = [];

    protected function request(array $payload): array
    {
        $this->lastPayload = $payload;

        return ['message' => ['role' => 'assistant', 'content' => 'ok'], 'done' => true];
    }
}

describe('OllamaProvider reasoning effort', function () {
    beforeEach(function () {
        $this->provider = new TestableOllamaEffortProvider('http://localhost:11434');
        $this->chat = fn (array $options) => $this->provider->chat([Message::user('hi')], $options);
    });

    it('turns thinking on for any level that thinks', function () {
        ($this->chat)(['effort' => 'high']);

        expect($this->provider->lastPayload['think'])->toBeTrue();
    });

    it('turns thinking off for none', function () {
        ($this->chat)(['effort' => 'none']);

        expect($this->provider->lastPayload['think'])->toBeFalse();
    });

    it('sends a level instead of a boolean on models that take one', function () {
        // gpt-oss builds accept "low"/"medium"/"high" where others take only true or false.
        ($this->chat)(['effort' => 'medium', 'model' => 'gpt-oss:20b']);

        expect($this->provider->lastPayload['think'])->toBe('medium');
    });

    it('sends nothing when the caller does not ask', function () {
        ($this->chat)([]);

        expect($this->provider->lastPayload)->not->toHaveKey('think');
    });

    it('rejects a level it does not recognise', function () {
        expect(fn () => ($this->chat)(['effort' => 'enormous']))
            ->toThrow(InvalidArgumentException::class, 'enormous');
    });

    it('accepts a provider-level default the call can override', function () {
        $provider = new TestableOllamaEffortProvider('http://localhost:11434', 'llama3.1', 4096, Effort::High);

        $provider->chat([Message::user('hi')], []);
        expect($provider->lastPayload['think'])->toBeTrue();

        $provider->chat([Message::user('hi')], ['effort' => 'none']);
        expect($provider->lastPayload['think'])->toBeFalse();
    });
});
