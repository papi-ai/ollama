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
use PapiAI\Core\Factory\ComposerDiscovery;
use PapiAI\Ollama\OllamaProvider;
use PapiAI\Ollama\OllamaProviderFactory;

describe('OllamaProviderFactory', function () {
    beforeEach(function () {
        $this->factory = new OllamaProviderFactory();
        $this->property = fn (object $provider, string $name): mixed => (fn () => $this->{$name})->call($provider);
    });

    it('is named after its package', function () {
        expect($this->factory->name())->toBe('ollama');
        expect($this->factory->package())->toBe('papi-ai/ollama');
    });

    it('is declared in composer.json, where discovery looks for it', function () {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../../composer.json'), true);

        expect($manifest['name'])->toBe($this->factory->package());
        expect($manifest['type'])->toBe(ComposerDiscovery::PACKAGE_TYPE);
        expect($manifest['extra']['papi']['factory'])->toBe(OllamaProviderFactory::class);
    });

    it('takes what the constructor takes', function () {
        expect(array_map(fn ($setting) => $setting->name, $this->factory->settings()))->toBe(['base_url', 'model', 'max_tokens', 'effort']);
    });

    it('builds the provider from its settings', function () {
        $provider = $this->factory->create([
            'base_url' => 'http://gpu.test:11434',
            'model' => 'llama3.1',
            'max_tokens' => 1024,
            'effort' => 'high',
        ]);

        expect($provider)->toBeInstanceOf(OllamaProvider::class);
        expect(($this->property)($provider, 'baseUrl'))->toBe('http://gpu.test:11434');
        expect(($this->property)($provider, 'defaultModel'))->toBe('llama3.1');
        expect(($this->property)($provider, 'defaultMaxTokens'))->toBe(1024);
        expect(($this->property)($provider, 'defaultEffort'))->toBe(Effort::High);
    });

    it('leaves what it is not given to the provider\'s own defaults', function () {
        $provider = $this->factory->create([]);

        expect(($this->property)($provider, 'baseUrl'))->toBe('http://localhost:11434');
    });

    it('needs no settings at all', function () {
        expect($this->factory->create([]))->toBeInstanceOf(OllamaProvider::class);
    });
});
