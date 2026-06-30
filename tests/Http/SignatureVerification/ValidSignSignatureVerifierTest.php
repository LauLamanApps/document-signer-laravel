<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Laravel\Tests\Http\SignatureVerification;

use LauLamanApps\DocumentSigner\Laravel\Http\SignatureVerification\ValidSignSignatureVerifier;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidSignSignatureVerifierTest extends TestCase
{
    #[Test]
    public function it_accepts_token_via_query_parameter(): void
    {
        $request = Request::create('/x?token=secret-xyz', 'POST');
        self::assertTrue((new ValidSignSignatureVerifier('secret-xyz'))->verify($request));
    }

    #[Test]
    public function it_accepts_token_via_x_callback_key_header(): void
    {
        $request = Request::create('/x', 'POST', server: ['HTTP_X_CALLBACK_KEY' => 'secret-xyz']);
        self::assertTrue((new ValidSignSignatureVerifier('secret-xyz'))->verify($request));
    }

    #[Test]
    public function it_accepts_token_via_x_callback_token_header(): void
    {
        $request = Request::create('/x', 'POST', server: ['HTTP_X_CALLBACK_TOKEN' => 'secret-xyz']);
        self::assertTrue((new ValidSignSignatureVerifier('secret-xyz'))->verify($request));
    }

    #[Test]
    public function it_rejects_wrong_token(): void
    {
        $request = Request::create('/x?token=not-the-token', 'POST');
        self::assertFalse((new ValidSignSignatureVerifier('secret-xyz'))->verify($request));
    }

    #[Test]
    public function it_rejects_when_no_token_present(): void
    {
        $request = Request::create('/x', 'POST');
        self::assertFalse((new ValidSignSignatureVerifier('secret-xyz'))->verify($request));
    }

    #[Test]
    public function it_rejects_when_secret_is_missing(): void
    {
        $request = Request::create('/x?token=anything', 'POST');
        self::assertFalse((new ValidSignSignatureVerifier(null))->verify($request));
        self::assertFalse((new ValidSignSignatureVerifier(''))->verify($request));
    }
}
