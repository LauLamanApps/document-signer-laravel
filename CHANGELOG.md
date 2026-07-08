# Changelog

All notable changes to `laulamanapps/document-signer-laravel` are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [2.3.1] - 2026-07-08

### Added

- Expose DocuSign's field-placement fine-tune as the `anchor_y_offset_pixels`
  provider config value (env `DOCUSIGN_ANCHOR_Y_OFFSET_PIXELS`), passed through
  to `DocuSignConfig`. Positive moves every field down the page, negative up;
  default 0. Requires `laulamanapps/document-signer-docusign` ≥ 2.3.2.

## [2.3.0] - 2026-07-08

### Changed — single-document download is now provider-agnostic

`DocumentSigner::downloadSignedDocument($providerEnvelopeId, $documentId)` (via
the manager, facade, or the recipient-override wrapper) now takes the **caller's
`Document::$id`** uniformly across every provider — including DocuSign, which
previously required its own positional id. No API changed in this package; the
behaviour flows through from the SDK and provider packages.

Consumers can drop any "download the whole ZIP and match the filename"
workaround: a single document is retrieved directly by the id you assigned when
building the envelope. When the document isn't finalized yet, the call throws
the retryable `SignedDocumentUnavailableException` from the SDK.

### Upgrade notes

- Requires `laulamanapps/document-signer-sdk` ≥ 2.3.0, and (if used)
  `laulamanapps/document-signer-docusign` ≥ 2.3.0 /
  `laulamanapps/document-signer-validsign` ≥ 2.3.0.
- See the DocuSign package changelog for how ids resolve on envelopes sent
  before 2.3.0 (they match by document name).
