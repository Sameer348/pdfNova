<?php
/**
 * Minimal cURL helper for adapters (JSON requests, multipart uploads,
 * streaming downloads). TLS verification is always on.
 */

declare(strict_types=1);

namespace PDFNova\Converters;

final class Http
{
    /** @return array{status:int, body:string} */
    public static function request(string $method, string $url, array $headers = [], mixed $body = null, int $timeout = 120): array
    {
        if (!\function_exists('curl_init')) {
            throw new ConversionException('The server cannot reach external services (cURL is not enabled).', 'engine_failed');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        if ($response === false) {
            throw new ConversionException('The conversion service could not be reached. Please try again later.', 'engine_failed',
                new \RuntimeException($error));
        }
        return ['status' => $status, 'body' => (string) $response];
    }

    public static function json(string $method, string $url, array $headers = [], ?array $payload = null, int $timeout = 60): array
    {
        $headers[] = 'Accept: application/json';
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        $r = self::request($method, $url, $headers, $payload !== null ? json_encode($payload) : null, $timeout);
        $data = json_decode($r['body'], true);
        return ['status' => $r['status'], 'data' => \is_array($data) ? $data : []];
    }

    /** Download a URL straight to a file (no memory spike for big results). */
    public static function download(string $url, string $outPath, int $maxBytes = 500 * 1024 * 1024): void
    {
        $fh = fopen($outPath, 'wb');
        if ($fh === false) {
            throw new ConversionException('The result could not be saved.', 'save_failed');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh, CURLOPT_TIMEOUT => 180, CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => static fn($c, $dlTotal, $dl) => $dl > $maxBytes ? 1 : 0, // abort oversized downloads
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        fclose($fh);
        if ($ok === false || $status >= 400 || filesize($outPath) === 0) {
            @unlink($outPath);
            throw new ConversionException('The converted file could not be downloaded from the conversion service.', 'engine_failed');
        }
    }
}
