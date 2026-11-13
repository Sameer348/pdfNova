<?php
/** Builds the converter selected in config (converter.provider). */

declare(strict_types=1);

namespace PDFNova\Converters;

final class ConverterFactory
{
    public static function make(string $provider, string $apiKey): ConverterInterface
    {
        return match ($provider) {
            'cloudconvert' => $apiKey !== '' ? new CloudConvertConverter($apiKey) : new NullConverter(),
            default        => new NullConverter(),
        };
    }

    /**
     * Conversion definition for a tool, from services/converters/<slug>.php:
     * ['from' => [...extensions], 'to' => 'docx', 'label' => 'Word document'].
     */
    public static function definition(string $toolSlug): array
    {
        if (!preg_match('/^[a-z0-9-]+$/', $toolSlug)) {
            throw new \InvalidArgumentException('Invalid tool');
        }
        $file = __DIR__ . '/' . $toolSlug . '.php';
        if (!is_file($file)) {
            throw new \InvalidArgumentException('No conversion definition for ' . $toolSlug);
        }
        return require $file;
    }
}
