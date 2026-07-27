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
    defaultModel: 'llama3.1',
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

| Model | Type |
|---|---|
| `llama3.1` (default) | General purpose |
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

## Requirements

- PHP 8.2+
- `ext-curl`
- `papi-ai/papi-core` ^0.14
- [Ollama](https://ollama.com) installed and running locally
