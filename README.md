# PapiAI Ollama Provider

[![Tests](https://github.com/papi-ai/ollama/workflows/CI/badge.svg)](https://github.com/papi-ai/ollama/actions?query=workflow%3ACI)

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
| `llama3.1` (default) | General purpose |
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

## License

MIT
