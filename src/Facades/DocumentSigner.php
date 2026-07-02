<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Laravel\Facades;

use LauLamanApps\DocumentSigner\Laravel\DocumentSignerManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \LauLamanApps\DocumentSigner\Sdk\Provider\SignatureProvider driver(?string $name = null)
 * @method static \LauLamanApps\DocumentSigner\Sdk\Provider\EnvelopeReceipt   send(\LauLamanApps\DocumentSigner\Sdk\Envelope\Envelope $envelope)
 * @method static \LauLamanApps\DocumentSigner\Sdk\Envelope\EnvelopeStatus    getStatus(string $providerEnvelopeId)
 * @method static string                                          downloadSigned(string $providerEnvelopeId)
 * @method static \SplFileInfo                                     downloadSignedDocument(string $providerEnvelopeId, string $documentId)
 * @method static void                                            cancel(string $providerEnvelopeId, ?string $reason = null)
 * @method static \LauLamanApps\DocumentSigner\Laravel\DocumentSignerManager   extend(string $name, \Closure $factory)
 * @method static \LauLamanApps\DocumentSigner\Laravel\DocumentSignerManager   set(string $name, \LauLamanApps\DocumentSigner\Sdk\Provider\SignatureProvider $provider)
 * @method static string                                          getDefaultDriver()
 *
 * @see \LauLamanApps\DocumentSigner\Laravel\DocumentSignerManager
 */
final class DocumentSigner extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DocumentSignerManager::class;
    }
}
