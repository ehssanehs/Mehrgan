<?php

namespace Tests\Support;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Telegram\Bot\HttpClients\HttpClientInterface;

/**
 * Records every outgoing Telegram request instead of hitting the network.
 *
 * The SDK's BotsManager is a final class, so Mockery cannot mock the
 * `Telegram` facade. The client is injected through
 * `config('telegram.http_client_handler')`, which is the seam the SDK itself
 * uses, so the real manager and the real message-building code still run.
 */
class FakeTelegramHttpClient implements HttpClientInterface
{
    /** @var array<int, array{url: string, method: string, payload: array}> */
    public array $requests = [];

    protected int $timeOut = 30;

    protected int $connectTimeOut = 10;

    public function send(
        string $url,
        string $method,
        array $headers = [],
        array $options = [],
        bool $isAsyncRequest = false
    ): ResponseInterface|PromiseInterface|null {
        $payload = $options['form_params'] ?? $options['json'] ?? [];

        $this->requests[] = [
            'url' => $url,
            'method' => $method,
            'payload' => is_array($payload) ? $payload : [],
        ];

        $chatId = is_array($payload) ? ($payload['chat_id'] ?? 1) : 1;

        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'ok' => true,
            'result' => [
                'message_id' => count($this->requests),
                'date' => time(),
                'chat' => ['id' => $chatId, 'type' => 'private'],
                'text' => is_array($payload) ? ($payload['text'] ?? '') : '',
            ],
        ]));
    }

    public function getTimeOut(): int
    {
        return $this->timeOut;
    }

    public function setTimeOut(int $timeOut): static
    {
        $this->timeOut = $timeOut;

        return $this;
    }

    public function getConnectTimeOut(): int
    {
        return $this->connectTimeOut;
    }

    public function setConnectTimeOut(int $connectTimeOut): static
    {
        $this->connectTimeOut = $connectTimeOut;

        return $this;
    }

    /**
     * Every payload that was sent through the SDK, in order.
     *
     * @return array<int, array>
     */
    public function payloads(): array
    {
        return array_map(fn (array $request): array => $request['payload'], $this->requests);
    }

    /**
     * Payloads for a single Bot API method (e.g. "sendMessage").
     *
     * @return array<int, array>
     */
    public function payloadsFor(string $apiMethod): array
    {
        return array_values(array_map(
            fn (array $request): array => $request['payload'],
            array_filter(
                $this->requests,
                fn (array $request): bool => str_ends_with($request['url'], '/'.$apiMethod),
            ),
        ));
    }
}
