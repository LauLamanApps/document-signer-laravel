<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Laravel\Tests\Http\Controllers;

use LauLamanApps\DocumentSigner\Laravel\Events\DocumentSignerWebhookReceived;
use LauLamanApps\DocumentSigner\Laravel\Http\Controllers\WebhookController;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WebhookControllerTest extends TestCase
{
    #[Test]
    public function it_dispatches_event_when_docusign_signature_matches(): void
    {
        $body = '{"event":"completed"}';
        $secret = 'shhh';
        $hmac = base64_encode(hash_hmac('sha256', $body, $secret, true));

        $request = Request::create('/x', 'POST',
            server: [
                'HTTP_X_DOCUSIGN_SIGNATURE_1' => $hmac,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: $body);

        [$controller, $captured] = $this->buildController([
            'document-signer.webhooks.docusign.hmac_secret' => $secret,
        ]);

        $response = $controller->docusign($request);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $captured);
        self::assertInstanceOf(DocumentSignerWebhookReceived::class, $captured[0]);
        self::assertSame('docusign', $captured[0]->driver);
        self::assertSame(['event' => 'completed'], $captured[0]->payload);
    }

    #[Test]
    public function it_returns_401_when_docusign_signature_is_invalid(): void
    {
        $request = Request::create('/x', 'POST',
            server: ['HTTP_X_DOCUSIGN_SIGNATURE_1' => 'wrong'],
            content: '{"a":1}');

        [$controller, $captured] = $this->buildController([
            'document-signer.webhooks.docusign.hmac_secret' => 'shhh',
        ]);

        $response = $controller->docusign($request);

        self::assertSame(401, $response->getStatusCode());
        self::assertCount(0, $captured);
    }

    #[Test]
    public function it_dispatches_event_when_validsign_token_matches(): void
    {
        $body = '{"package":"x"}';
        $request = Request::create('/x', 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('validsign:secret-token'),
            ],
            content: $body);

        [$controller, $captured] = $this->buildController([
            'document-signer.webhooks.validsign.callback_secret' => 'secret-token',
        ]);

        $response = $controller->validsign($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $captured);
        self::assertSame('validsign', $captured[0]->driver);
        self::assertSame(['package' => 'x'], $captured[0]->payload);
    }

    #[Test]
    public function it_returns_401_when_validsign_token_is_wrong(): void
    {
        $request = Request::create('/x', 'POST',
            server: ['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('validsign:wrong')],
            content: '{}');

        [$controller, $captured] = $this->buildController([
            'document-signer.webhooks.validsign.callback_secret' => 'right',
        ]);

        self::assertSame(401, $controller->validsign($request)->getStatusCode());
        self::assertCount(0, $captured);
    }

    /**
     * @param array<string, mixed> $configDotMap
     * @return array{0: WebhookController, 1: \ArrayObject<int, DocumentSignerWebhookReceived>}
     */
    private function buildController(array $configDotMap): array
    {
        $items = [];
        foreach ($configDotMap as $dotKey => $value) {
            $this->setByDot($items, $dotKey, $value);
        }
        $config = new Repository($items);

        $dispatcher = new Dispatcher(new Container());
        $captured = new \ArrayObject();
        $dispatcher->listen(DocumentSignerWebhookReceived::class, static function ($event) use ($captured): void {
            $captured[] = $event;
        });

        return [new WebhookController($config, $dispatcher), $captured];
    }

    /**
     * @param array<string, mixed> $target
     */
    private function setByDot(array &$target, string $dotKey, mixed $value): void
    {
        $segments = explode('.', $dotKey);
        $cursor = &$target;
        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $cursor[$segment] = $value;
                return;
            }
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
    }
}
