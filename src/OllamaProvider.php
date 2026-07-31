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

namespace PapiAI\Ollama;

use Generator;
use PapiAI\Core\Contracts\EmbeddingProviderInterface;
use PapiAI\Core\Contracts\ProviderInterface;
use PapiAI\Core\EmbeddingResponse;
use PapiAI\Core\Exception\AuthenticationException;
use PapiAI\Core\Exception\ProviderException;
use PapiAI\Core\Exception\RateLimitException;
use PapiAI\Core\Message;
use PapiAI\Core\Response;
use PapiAI\Core\Role;
use PapiAI\Core\StreamChunk;
use PapiAI\Core\ToolCall;
use PapiAI\Core\ToolChoice;
use RuntimeException;

/**
 * Ollama API provider for PapiAI.
 *
 * Bridges PapiAI's core types (Message, Response, ToolCall) with Ollama's OpenAI-compatible
 * API, handling format conversion in both directions. Supports chat completions, streaming,
 * tool calling, vision (multimodal), structured JSON output, and text embeddings.
 *
 * Authentication is optional (local by default). Configurable base URL defaults to
 * localhost:11434. All HTTP is done with ext-curl directly, with no HTTP abstraction layer.
 *
 * Common model families:
 * - llama3.1 (general purpose)
 * - codellama (code generation)
 * - mistral / mixtral (general purpose)
 * - qwen2.5-coder (code generation)
 * - nomic-embed-text (embeddings)
 *
 * @see https://github.com/ollama/ollama/blob/main/docs/openai.md
 *
 * @psalm-import-type ChatOptions from ProviderInterface *
 * The neutral `effort` option is accepted and ignored here. Ollama does expose a think parameter, per model, but papi does not map it yet, so the option is accepted and ignored for now. Ignoring it
 * degrades nothing the caller was promised, which is why it is silent where an unhonourable
 * `toolChoice` throws.
 */
class OllamaProvider implements ProviderInterface, EmbeddingProviderInterface
{
    /**
     * Create a new Ollama provider instance.
     *
     * @param string $baseUrl         Ollama server URL (defaults to localhost:11434)
     * @param string $defaultModel    Model to use when not specified in options
     * @param int    $defaultMaxTokens Maximum output tokens when not specified in options
     */
    public function __construct(
        private readonly string $baseUrl = 'http://localhost:11434',
        private readonly string $defaultModel = 'llama3.1',
        private readonly int $defaultMaxTokens = 4096,
    ) {
    }

    /**
     * Send a chat completion request to the Ollama API.
     *
     * Converts PapiAI Messages to OpenAI-compatible format, sends the request,
     * and parses the response back into a core Response object. Supports tools,
     * vision, structured output, and custom generation parameters.
     *
     * @param array<Message> $messages Conversation history as PapiAI Message objects
     * @param ChatOptions     $options  Request options (model, tools, maxTokens, temperature, toolChoice, etc.)
     *
     * @return Response Parsed response containing text, tool calls, usage, and stop reason
     *
     * @throws AuthenticationException When authentication fails (HTTP 401)
     * @throws RateLimitException      When rate limits are exceeded (HTTP 429)
     * @throws ProviderException       When the API returns any other error (HTTP 4xx/5xx)
     * @throws RuntimeException        When the cURL request itself fails
     */
    public function chat(array $messages, array $options = []): Response
    {
        $payload = $this->buildPayload($messages, $options);
        $response = $this->request($payload);

        return Response::fromOpenAI($response, $messages);
    }

    /**
     * Stream a chat completion from the Ollama API using server-sent events.
     *
     * Yields StreamChunk objects as partial responses arrive. The final chunk
     * has isComplete=true. Only text content is streamed.
     *
     * @param array<Message> $messages Conversation history as PapiAI Message objects
     *
     * @return iterable<StreamChunk> Stream of text chunks, ending with a completion marker
     *
     * @throws RuntimeException When the cURL request fails
     */
    public function stream(array $messages, array $options = []): iterable
    {
        $payload = $this->buildPayload($messages, $options);
        $payload['stream'] = true;

        foreach ($this->streamRequest($payload) as $event) {
            $delta = $event['choices'][0]['delta'] ?? [];
            if (isset($delta['content'])) {
                yield new StreamChunk($delta['content']);
            }
            if (($event['choices'][0]['finish_reason'] ?? null) !== null) {
                yield new StreamChunk('', isComplete: true);
            }
        }
    }

    /**
     * Whether this provider supports function/tool calling.
     *
     * Ollama supports tool calling via its OpenAI-compatible API for models
     * that have been trained with tool-use capabilities.
     *
     * @return bool Always true for Ollama
     */
    public function supportsTool(): bool
    {
        return true;
    }

    /**
     * Whether this provider supports vision (image inputs in messages).
     *
     * Ollama supports multimodal inputs for vision-capable models (e.g. llava).
     *
     * @return bool Always true for Ollama
     */
    public function supportsVision(): bool
    {
        return true;
    }

    /**
     * Whether this provider supports structured JSON output.
     *
     * Ollama supports JSON mode via the 'format' parameter.
     *
     * @return bool Always true for Ollama
     */
    public function supportsStructuredOutput(): bool
    {
        return true;
    }

    /**
     * Get the provider identifier.
     *
     * @return string The provider name 'ollama'
     */
    public function getName(): string
    {
        return 'ollama';
    }

    /**
     * Generate text embeddings via the Ollama embedding API.
     *
     * Uses Ollama's native /api/embed endpoint (not the OpenAI-compatible one)
     * for embedding generation. Defaults to the nomic-embed-text model.
     *
     * @param string|array<string> $input Text or array of texts to embed
     *
     * @return EmbeddingResponse The embedding vectors and metadata
     *
     * @throws AuthenticationException When authentication fails (HTTP 401)
     * @throws RateLimitException      When rate limits are exceeded (HTTP 429)
     * @throws ProviderException       When the API returns any other error (HTTP 4xx/5xx)
     * @throws RuntimeException        When the cURL request itself fails
     */
    public function embed(string|array $input, array $options = []): EmbeddingResponse
    {
        $input = is_string($input) ? [$input] : $input;
        $model = $options['model'] ?? 'nomic-embed-text';

        $payload = [
            'model' => $model,
            'input' => $input,
        ];

        $response = $this->embeddingRequest($payload);

        return new EmbeddingResponse(
            embeddings: $response['embeddings'],
            model: $response['model'] ?? $model,
            usage: [],
        );
    }

    /**
     * Make an embedding API request to Ollama's native /api/embed endpoint.
     *
     * @param array $payload The embedding request payload (model, input)
     *
     * @return array The decoded JSON response containing embeddings
     *
     * @throws AuthenticationException When authentication fails (HTTP 401)
     * @throws RateLimitException      When rate limits are exceeded (HTTP 429)
     * @throws ProviderException       When the API returns any other error (HTTP 4xx/5xx)
     * @throws RuntimeException        When the cURL request itself fails
     */
    protected function embeddingRequest(array $payload): array
    {
        $url = $this->baseUrl . '/api/embed';
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error !== '') {
            throw new RuntimeException("Ollama embedding API request failed: {$error}");
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $this->throwForStatusCode($httpCode, $data);
        }

        return $data;
    }

    /**
     * Build the API request payload.
     */
    private function buildPayload(array $messages, array $options): array
    {
        $apiMessages = [];

        foreach ($messages as $message) {
            if ($message instanceof Message) {
                $apiMessages[] = $this->convertMessage($message);
            }
        }

        $payload = [
            'model' => $options['model'] ?? $this->defaultModel,
            'messages' => $apiMessages,
        ];

        if (isset($options['maxTokens'])) {
            $payload['max_tokens'] = $options['maxTokens'];
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        if (isset($options['stopSequences'])) {
            $payload['stop'] = $options['stopSequences'];
        }

        // Handle structured output / JSON mode
        // Ollama uses 'format' => 'json' instead of OpenAI's response_format with json_schema
        if (isset($options['outputSchema'])) {
            $payload['format'] = 'json';
        }

        // Handle tools
        if (isset($options['tools']) && !empty($options['tools'])) {
            $payload['tools'] = $this->convertTools($options['tools']);
        }

        // Ollama's /api/chat has no forced-tool-choice mechanism. Validate the option and fail loudly
        // for anything other than "auto", rather than silently ignoring the caller's guarantee.
        if (isset($options['toolChoice'])) {
            $choice = ToolChoice::fromOption($options['toolChoice'], $options['tools'] ?? []);

            if (!$choice->isAuto()) {
                throw new ProviderException(
                    'Ollama does not support forced tool choice; only "auto" is available.',
                    $this->getName(),
                );
            }
        }

        return $payload;
    }

    /**
     * Convert a Message to OpenAI-compatible API format.
     */
    private function convertMessage(Message $message): array
    {
        $apiMessage = [
            'role' => $this->convertRole($message->role),
        ];

        if ($message->isTool()) {
            $apiMessage['role'] = 'tool';
            $apiMessage['content'] = $message->content;
            $apiMessage['tool_call_id'] = $message->toolCallId;
        } elseif ($message->hasToolCalls()) {
            $apiMessage['content'] = $message->getText() ?: null;
            $apiMessage['tool_calls'] = array_map(function (ToolCall $tc) {
                return [
                    'id' => $tc->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $tc->name,
                        'arguments' => json_encode($tc->arguments),
                    ],
                ];
            }, $message->toolCalls);
        } elseif (is_array($message->content)) {
            $apiMessage['content'] = $this->convertMultimodalContent($message->content);
        } else {
            $apiMessage['content'] = $message->content;
        }

        return $apiMessage;
    }

    /**
     * Convert multimodal content to OpenAI-compatible format.
     */
    private function convertMultimodalContent(array $content): array
    {
        $parts = [];

        foreach ($content as $part) {
            if ($part['type'] === 'text') {
                $parts[] = ['type' => 'text', 'text' => $part['text']];
            } elseif ($part['type'] === 'image') {
                $source = $part['source'];
                if ($source['type'] === 'url') {
                    $parts[] = [
                        'type' => 'image_url',
                        'image_url' => ['url' => $source['url']],
                    ];
                } else {
                    $parts[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => "data:{$source['media_type']};base64,{$source['data']}",
                        ],
                    ];
                }
            }
        }

        return $parts;
    }

    /**
     * Convert tools from PapiAI format to OpenAI-compatible format.
     */
    private function convertTools(array $tools): array
    {
        $ollamaTools = [];

        foreach ($tools as $tool) {
            if (is_array($tool)) {
                $ollamaTools[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'description' => $tool['description'],
                        'parameters' => $tool['input_schema'] ?? $tool['parameters'] ?? ['type' => 'object', 'properties' => []],
                    ],
                ];
            }
        }

        return $ollamaTools;
    }

    /**
     * Convert Role to OpenAI-compatible role string.
     */
    private function convertRole(Role $role): string
    {
        return match ($role) {
            Role::System => 'system',
            Role::User => 'user',
            Role::Assistant => 'assistant',
            Role::Tool => 'tool',
        };
    }

    /**
     * Make a chat completion API request to Ollama's OpenAI-compatible endpoint.
     *
     * @param array $payload The chat completion request payload
     *
     * @return array The decoded JSON response
     *
     * @throws AuthenticationException When authentication fails (HTTP 401)
     * @throws RateLimitException      When rate limits are exceeded (HTTP 429)
     * @throws ProviderException       When the API returns any other error (HTTP 4xx/5xx)
     * @throws RuntimeException        When the cURL request itself fails
     */
    protected function request(array $payload): array
    {
        $url = $this->baseUrl . '/v1/chat/completions';
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error !== '') {
            throw new RuntimeException("Ollama API request failed: {$error}");
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $this->throwForStatusCode($httpCode, $data);
        }

        return $data;
    }

    /**
     * Throw the appropriate exception based on HTTP status code.
     *
     * Maps HTTP 401 to AuthenticationException, 429 to RateLimitException,
     * and all other 4xx/5xx codes to ProviderException.
     *
     * @param int        $httpCode The HTTP response status code
     * @param array|null $data     The decoded JSON error response body
     *
     * @throws AuthenticationException When authentication fails (HTTP 401)
     * @throws RateLimitException      When rate limits are exceeded (HTTP 429)
     * @throws ProviderException       For all other HTTP errors
     */
    protected function throwForStatusCode(int $httpCode, ?array $data): never
    {
        $errorMessage = $data['error']['message'] ?? 'Unknown error';

        if ($httpCode === 401) {
            throw new AuthenticationException(
                $this->getName(),
                $httpCode,
                $data,
            );
        }

        if ($httpCode === 429) {
            throw new RateLimitException(
                $this->getName(),
                statusCode: $httpCode,
                responseBody: $data,
            );
        }

        throw new ProviderException(
            "Ollama API error ({$httpCode}): {$errorMessage}",
            $this->getName(),
            $httpCode,
            $data,
        );
    }

    /**
     * Make a streaming chat completion request to Ollama's OpenAI-compatible endpoint.
     *
     * Buffers the full SSE response, then parses and yields individual events.
     * Each yielded array represents one SSE data frame in OpenAI chat completion format.
     *
     * @param array $payload The chat completion request payload (must include stream=true)
     *
     * @return Generator<array> Parsed SSE events as associative arrays
     */
    protected function streamRequest(array $payload): Generator
    {
        $url = $this->baseUrl . '/v1/chat/completions';
        $ch = curl_init($url);

        $buffer = '';
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$buffer) {
                $buffer .= $data;

                return strlen($data);
            },
        ]);

        curl_exec($ch);
        curl_close($ch);

        // Parse SSE events
        $lines = explode("\n", $buffer);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'data: ')) {
                $json = substr($line, 6);
                if ($json === '[DONE]') {
                    break;
                }
                $event = json_decode($json, true);
                if ($event !== null) {
                    yield $event;
                }
            }
        }
    }
}
