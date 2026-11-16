# Ollama

Ollama provider for PapiAI. Run AI models locally with no API key required.

## Installation

```bash
composer require papi-ai/ollama
```

## Usage

```php
use PapiAI\Core\Agent;
use PapiAI\Ollama\OllamaProvider;

// No API key needed — runs locally
$provider = new OllamaProvider(
    baseUrl: 'http://localhost:11434',  // default
    defaultModel: 'llama3.1', // required: Ollama has no vendor default
);

$agent = new Agent(
    provider: $provider,
    instructions: 'You are a helpful assistant.',
);

$response = $agent->run('Hello!');
echo $response->text;
```

No API key is required. Ollama runs models locally on your machine.

## Models

Ollama serves whatever you have pulled, so there is no default model. Name one per call or in the constructor; without either, `chat()` throws before any request and points you at `ollama list`.


| Model | Type |
|---|---|
| `llama3.1` | General purpose |
| `codellama` | Code generation |
| `mistral` | General purpose |
| `mixtral` | Mixture of experts |
| `qwen2.5-coder` | Code generation |
| `nomic-embed-text` | Embeddings (default) |

Any model available through Ollama can be used by passing its name as the model parameter.

## Capabilities

| Capability | Supported |
|---|---|
| Chat | Yes |
| Streaming | Yes |
| Tool calling | Yes |
| Vision | Yes |
| Structured output | Yes |
| Embeddings | Yes |

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

## Requirements

- PHP 8.2+
- `ext-curl`
- `papi-ai/papi-core` ^0.14
- [Ollama](https://ollama.com) installed and running locally
