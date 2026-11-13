# Connecting conversion and OCR services

Hostinger shared hosting cannot run LibreOffice, Tesseract or other conversion
binaries. PDFNova therefore runs these tools through **adapters** that call an
external service. Until a service is configured, the tool pages say plainly that
the tool is not available yet — nothing is faked.

| Tools | Adapter folder | Ready-made adapter |
|---|---|---|
| PDF → Word / Excel / PowerPoint, Word / Excel / PowerPoint → PDF | `services/converters/` | CloudConvert (`CloudConvertConverter.php`) |
| OCR PDF (searchable PDF) | `services/ocr/` | OCR.space (`OcrSpaceOcr.php`) |

> ⚠ Both ready-made adapters follow the providers' documented APIs but were
> **not tested against the live services** (that needs your API key). After
> adding a key, run one conversion of each type and check `storage/logs/app.log`
> if anything fails.

> 🔒 **Privacy:** with an adapter enabled, uploaded files are sent to that
> provider. Update your Privacy Policy accordingly (the Phase 15 policy has a
> placeholder for this).

---

## 1. Office ⇄ PDF with CloudConvert

1. Create an account at <https://cloudconvert.com> and an **API key** (Dashboard →
   API → API Keys) with the scopes `task.read` and `task.write`.
2. In `public_html/includes/config.php`:
   ```php
   'converter' => [
       'provider' => 'cloudconvert',
       'api_key'  => 'PASTE-YOUR-KEY',
   ],
   ```
3. Make sure PHP's **cURL** extension is enabled (hPanel → Advanced → PHP
   Configuration → Extensions). It is on by default on Hostinger.
4. Reload a converter page, e.g. `/pdf-to-word`: the "not available" notice
   disappears and the uploader appears. The homepage "Coming soon" badges also
   disappear automatically.

How it works (`CloudConvertConverter::convert()`): create a job
(`import/upload → convert → export/url`), upload the file to the signed form,
poll the job every 2 s (up to 150 s), download the result.

Which formats each tool converts is defined in one small file per tool:
`services/converters/pdf-to-word.php`, `pdf-to-excel.php`, … (`from`, `to`, `label`).

## 2. OCR with OCR.space

1. Get a free or paid key at <https://ocr.space/ocrapi>.
2. In `config.php`:
   ```php
   'ocr' => [
       'provider' => 'ocrspace',
       'api_key'  => 'PASTE-YOUR-KEY',
   ],
   ```
3. The free key is limited (about 1 MB per file and 3 PDF pages). Paid keys use
   a different endpoint — change `OcrSpaceOcr::ENDPOINT` if you upgrade.

The adapter asks for a searchable PDF (`isCreateSearchablePdf=true`, invisible
text layer) and downloads it.

## 3. Adding another provider (e.g. ConvertAPI, Adobe PDF Services, a self-hosted Gotenberg)

1. Create a class in `services/converters/` implementing `ConverterInterface`:
   ```php
   namespace PDFNova\Converters;

   final class MyProviderConverter implements ConverterInterface
   {
       public function __construct(private readonly string $apiKey) {}
       public function name(): string { return 'myprovider'; }
       public function convert(string $inPath, string $from, string $to, string $outPath): void
       {
           // send $inPath to the service, write the converted file to $outPath,
           // throw new ConversionException('user-friendly message') on failure
       }
   }
   ```
   `Http::json()`, `Http::request()` (multipart with `CURLFile`) and
   `Http::download()` handle the HTTP calls with TLS verification on.
2. Register it in `ConverterFactory::make()` and add its name to the supported
   list in `external_api_configured()` (`includes/tools.php`).
3. Set `converter.provider` to its name in `config.php`.

OCR providers work the same way with `OcrInterface`, `OcrFactory` and the
`ocr` config section.

## 4. Unlock PDF and Compress PDF need no service

- **Unlock** runs in pure PHP (our own decryption, RC4/AES-128/AES-256).
- **Compress** runs in pure PHP + GD. If you move to a VPS with Ghostscript,
  set `engines.ghostscript` to its path for stronger compression.
