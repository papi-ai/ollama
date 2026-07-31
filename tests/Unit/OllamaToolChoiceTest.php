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

use PapiAI\Core\Contracts\NamedToolSelectableInterface;
use PapiAI\Core\Contracts\ToolSelectableInterface;
use PapiAI\Core\Exception\ProviderException;
use PapiAI\Core\Message;
use PapiAI\Ollama\OllamaProvider;

/**
 * Captures the request payload so tool-choice behaviour can be asserted without HTTP.
 */
class TestableOllamaToolChoiceProvider extends OllamaProvider
{
    public array $lastPayload = [];

    protected function request(array $payload): array
    {
        $this->lastPayload = $payload;

        return ['message' => ['role' => 'assistant', 'content' => 'ok'], 'done' => true];
    }
}

describe('OllamaProvider tool choice', function () {
    beforeEach(function () {
        $this->provider = new TestableOllamaToolChoiceProvider('http://localhost:11434');
        $this->tools = [
            ['name' => 'get_weather', 'description' => 'Weather', 'parameters' => ['type' => 'object']],
        ];
    });

    it('allows auto as a no-op (Ollama has no tool_choice mechanism)', function () {
        $this->provider->chat([Message::user('hi')], ['tools' => $this->tools, 'toolChoice' => 'auto']);

        expect($this->provider->lastPayload)->not->toHaveKey('tool_choice');
    });

    it('emits nothing when toolChoice is absent (backward compatible)', function () {
        $this->provider->chat([Message::user('hi')], ['tools' => $this->tools]);

        expect($this->provider->lastPayload)->not->toHaveKey('tool_choice');
    });

    it('throws (never silently drops) for required/none/specific, before any HTTP call', function () {
        foreach (['required', 'none', ['name' => 'get_weather']] as $choice) {
            expect(fn () => $this->provider->chat([Message::user('hi')], ['tools' => $this->tools, 'toolChoice' => $choice]))
                ->toThrow(ProviderException::class);
        }

        expect($this->provider->lastPayload)->toBe([]);
    });

    it('throws for an unknown toolChoice value', function () {
        expect(fn () => $this->provider->chat([Message::user('hi')], ['tools' => $this->tools, 'toolChoice' => 'always']))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('OllamaProvider tool-selection capability', function () {
    it('declares what it can force, so callers can ask instead of catching', function () {
        // Ollama has no forced-choice mechanism at all, so it claims neither capability.
        expect(is_subclass_of(OllamaProvider::class, ToolSelectableInterface::class))->toBeFalse();
        expect(is_subclass_of(OllamaProvider::class, NamedToolSelectableInterface::class))->toBeFalse();
    });
});
