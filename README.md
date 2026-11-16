# PapiAI Ollama Provider

[![CI](https://github.com/papi-ai/ollama/workflows/CI/badge.svg)](https://github.com/papi-ai/ollama/actions?query=workflow%3ACI) [![Latest Version](https://img.shields.io/packagist/v/papi-ai/ollama.svg)](https://packagist.org/packages/papi-ai/ollama) [![Total Downloads](https://img.shields.io/packagist/dt/papi-ai/ollama.svg)](https://packagist.org/packages/papi-ai/ollama) [![PHP Version](https://img.shields.io/packagist/php-v/papi-ai/ollama.svg)](https://packagist.org/packages/papi-ai/ollama) [![License](https://img.shields.io/packagist/l/papi-ai/ollama.svg)](https://packagist.org/packages/papi-ai/ollama)

Ollama provider for [PapiAI](https://github.com/papi-ai/papi-core) - A simple but powerful PHP library for building AI agents.

## Installation

```bash
composer require papi-ai/ollama
```

## Usage

```php
use PapiAI\Core\Agent;
use PapiAI\Ollama\OllamaProvider;

$provider = new OllamaProvider();

$agent = new Agent(
    provider: $provider,
    instructions: 'You are a helpful assistant.',
);

$response = $agent->run('Hello!');
echo $response->text;
```

## Available Models

The following models are supported (as referenced in `OllamaProvider`):

| Model | Type |
|---|---|
| `llama3.1` | General purpose |
| `codellama` | Code generation |
| `mistral` | General purpose |
| `mixtral` | Mixture of experts |
| `qwen2.5-coder` | Code generation |
| `nomic-embed-text` | Embeddings (default) |

## Features

- Tool/function calling
- Streaming support
- Embeddings support
- Vision support
- Structured output / JSON mode
- Runs locally via Ollama (no API key required)

## Creating It by Name

An application that picks its provider from configuration can build this one without naming
its class:

```php
use PapiAI\Core\Factory\ComposerDiscovery;
use PapiAI\Core\Factory\ProviderRegistry;

$registry = new ProviderRegistry(new ComposerDiscovery());

$provider = $registry->create('ollama', [
    'model' => 'llama3.1',
]);
```

It takes `base_url`, `model`, `max_tokens` and `effort`. Anything it is not given falls back to the
constructor's defaults, and a missing or unknown setting throws `ConfigurationException` before any
request is made.

## License

MIT
