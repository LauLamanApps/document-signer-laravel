<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Laravel\Http\SignatureVerification;

use Illuminate\Http\Request;

/**
 * Verifies ValidSign callback authenticity by comparing a configured shared
 * secret against either a `?token=...` query parameter or an `X-Callback-Key`
 * header. ValidSign administrators choose which delivery mode they configure;
 * accept either to keep configuration flexible.
 */
final class ValidSignSignatureVerifier implements WebhookSignatureVerifier
{
    public function __construct(
        private readonly ?string $secret,
    ) {}

    public function verify(Request $request): bool
    {
        if (!is_string($this->secret) || $this->secret === '') {
            return false;
        }

        $candidates = array_filter([
            $request->query('token'),
            $request->header('X-Callback-Key'),
            $request->header('X-Callback-Token'),
        ], static fn (mixed $v) => is_string($v) && $v !== '');

        foreach ($candidates as $given) {
            if (hash_equals($this->secret, (string) $given)) {
                return true;
            }
        }

        return false;
    }
}
