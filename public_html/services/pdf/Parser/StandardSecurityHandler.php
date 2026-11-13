<?php
/**
 * PDFNova — PDF Standard Security Handler (decryption), pure PHP.
 *
 * Implements the password algorithms of ISO 32000-1 §7.6.3/§7.6.4 and
 * ISO 32000-2 §7.6.4.3.3/§7.6.4.4 for revisions 2–6:
 *   R2  RC4 40-bit           R3  RC4 up to 128-bit
 *   R4  RC4 or AES-128 (crypt filters)
 *   R5  AES-256 (Adobe extension level 3)   R6  AES-256 (PDF 2.0)
 * Either the user ("open") password or the owner password unlocks the file.
 *
 * AES uses PHP's OpenSSL extension. RC4 is implemented here because
 * OpenSSL 3 no longer offers it by default.
 *
 * Not supported: public-key (certificate) encryption and third-party
 * security handlers — these are reported as unsupported, never guessed.
 *
 * Original code for PDFNova.
 */

declare(strict_types=1);

namespace PDFNova\Pdf\Parser;

use setasign\Fpdi\PdfParser\Type\PdfBoolean;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfHexString;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfString;
use setasign\Fpdi\PdfParser\Type\PdfType;

final class StandardSecurityHandler
{
    private const PAD = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08"
                      . "\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";

    private string $fileKey = '';
    private int $revision;
    private string $streamMethod; // 'RC4' | 'AESV2' | 'AESV3' | 'None'
    private string $stringMethod;
    private bool $encryptMetadata;

    /**
     * @param callable(PdfType): PdfType $resolve resolves indirect references (unencrypted read)
     * @throws EncryptionException
     */
    public function __construct(PdfDictionary $encrypt, string $firstFileId, string $password, callable $resolve)
    {
        $get = static fn(string $key): ?PdfType => isset($encrypt->value[$key]) ? $resolve($encrypt->value[$key]) : null;

        $filter = $get('Filter');
        if (!$filter instanceof PdfName || $filter->value !== 'Standard') {
            throw new EncryptionException(
                'This PDF uses certificate-based or third-party encryption, which can\'t be removed with a password.',
                'unsupported_encryption'
            );
        }

        $v = self::num($get('V'), 0);
        $this->revision = self::num($get('R'), 0);
        $em = $get('EncryptMetadata');
        $this->encryptMetadata = !($em instanceof PdfBoolean) || $em->value;

        // Which cipher protects streams and strings?
        if ($v === 1 || $v === 2) {
            $this->streamMethod = $this->stringMethod = 'RC4';
            $keyLength = $v === 1 ? 5 : intdiv(self::num($get('Length'), 40), 8);
        } elseif ($v === 4 || $v === 5) {
            $cf = $get('CF');
            $this->streamMethod = $this->cryptFilterMethod($cf, $get('StmF'), $resolve);
            $this->stringMethod = $this->cryptFilterMethod($cf, $get('StrF'), $resolve);
            $keyLength = $v === 5 ? 32 : 16;
            if ($v === 4 && ($this->streamMethod === 'RC4' || $this->stringMethod === 'RC4')) {
                $length = self::num($get('Length'), 128);
                $keyLength = $length > 16 ? intdiv($length, 8) : $length; // some writers give bytes
            }
        } else {
            throw new EncryptionException('This PDF uses an encryption version we don\'t support (V=' . $v . ').', 'unsupported_encryption');
        }
        $keyLength = max(5, min(32, $keyLength));

        $O = self::bytes($get('O'));
        $U = self::bytes($get('U'));
        $P = self::num($get('P'), 0);

        if ($this->revision >= 5) {
            $this->fileKey = $this->aes256Key($password, $O, $U, self::bytes($get('OE')), self::bytes($get('UE')));
        } elseif ($this->revision >= 2) {
            $this->fileKey = $this->rc4EraKey($password, $O, $U, $P, $firstFileId, $keyLength);
        } else {
            throw new EncryptionException('This PDF uses an unknown encryption revision.', 'unsupported_encryption');
        }
    }

    /* ================================================== key derivation */

    /** R2–R4: try as user password, then as owner password. */
    private function rc4EraKey(string $password, string $O, string $U, int $P, string $id, int $n): string
    {
        $key = $this->userKey($password, $O, $P, $id, $n);
        if ($this->userKeyValid($key, $U, $id)) {
            return $key;
        }

        // Owner password → recover the user password from /O (Algorithm 7).
        $h = md5(substr($password . self::PAD, 0, 32), true);
        if ($this->revision >= 3) {
            for ($i = 0; $i < 50; $i++) {
                $h = md5(substr($h, 0, $n), true);
            }
        }
        $ownerKey = substr($h, 0, $n);
        if ($this->revision === 2) {
            $userPassword = self::rc4($ownerKey, $O);
        } else {
            $userPassword = $O;
            for ($i = 19; $i >= 0; $i--) {
                $userPassword = self::rc4(self::xorKey($ownerKey, $i), $userPassword);
            }
        }
        $key = $this->userKey($userPassword, $O, $P, $id, $n, true);
        if ($this->userKeyValid($key, $U, $id)) {
            return $key;
        }
        throw new EncryptionException('The password is incorrect.', 'wrong_password');
    }

    /** Algorithm 2: file key from a (user) password. */
    private function userKey(string $password, string $O, int $P, string $id, int $n, bool $alreadyPadded = false): string
    {
        $input = substr(($alreadyPadded ? $password : $password . self::PAD), 0, 32)
            . $O . pack('V', $P & 0xFFFFFFFF) . $id;
        if ($this->revision >= 4 && !$this->encryptMetadata) {
            $input .= "\xFF\xFF\xFF\xFF";
        }
        $h = md5($input, true);
        if ($this->revision >= 3) {
            for ($i = 0; $i < 50; $i++) {
                $h = md5(substr($h, 0, $n), true);
            }
        }
        return substr($h, 0, $this->revision === 2 ? 5 : $n);
    }

    /** Algorithms 4/5: does this key reproduce /U ? */
    private function userKeyValid(string $key, string $U, string $id): bool
    {
        if ($this->revision === 2) {
            return hash_equals($U, self::rc4($key, self::PAD));
        }
        $x = self::rc4($key, md5(self::PAD . $id, true));
        for ($i = 1; $i <= 19; $i++) {
            $x = self::rc4(self::xorKey($key, $i), $x);
        }
        return \strlen($U) >= 16 && hash_equals(substr($U, 0, 16), $x);
    }

    /** R5/R6 (AES-256): validate the password and unwrap the file key from /UE or /OE. */
    private function aes256Key(string $password, string $O, string $U, string $OE, string $UE): string
    {
        if (\strlen($U) < 48 || \strlen($O) < 48 || \strlen($UE) < 32 || \strlen($OE) < 32) {
            throw new EncryptionException('This PDF\'s encryption data is damaged.', 'corrupt_pdf');
        }
        $pw = substr($password, 0, 127); // UTF-8, truncated as the spec requires
        $U48 = substr($U, 0, 48);

        // User password?
        if (hash_equals(substr($U, 0, 32), $this->hashR6($pw, substr($U, 32, 8), ''))) {
            return self::aesNoIv($this->hashR6($pw, substr($U, 40, 8), ''), substr($UE, 0, 32));
        }
        // Owner password?
        if (hash_equals(substr($O, 0, 32), $this->hashR6($pw, substr($O, 32, 8), $U48))) {
            return self::aesNoIv($this->hashR6($pw, substr($O, 40, 8), $U48), substr($OE, 0, 32));
        }
        throw new EncryptionException('The password is incorrect.', 'wrong_password');
    }

    /** R5: SHA-256. R6: the iterative hash of ISO 32000-2 Algorithm 2.B. */
    private function hashR6(string $password, string $salt, string $userData): string
    {
        $k = hash('sha256', $password . $salt . $userData, true);
        if ($this->revision === 5) {
            return $k;
        }
        for ($i = 0; ; $i++) {
            $k1 = str_repeat($password . $k . $userData, 64);
            $e = openssl_encrypt($k1, 'aes-128-cbc', substr($k, 0, 16), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, substr($k, 16, 16));
            if ($e === false) {
                throw new EncryptionException('AES is not available on this server.', 'engine_failed');
            }
            $mod = array_sum(unpack('C16', substr($e, 0, 16))) % 3;
            $k = hash(['sha256', 'sha384', 'sha512'][$mod], $e, true);
            if ($i >= 63 && \ord($e[\strlen($e) - 1]) <= $i - 31) {
                break;
            }
        }
        return substr($k, 0, 32);
    }

    /* ======================================================= decryption */

    public function decryptString(string $data, int $objectNumber, int $generation): string
    {
        return $this->decrypt($this->stringMethod, $data, $objectNumber, $generation);
    }

    public function decryptStream(string $data, int $objectNumber, int $generation): string
    {
        return $this->decrypt($this->streamMethod, $data, $objectNumber, $generation);
    }

    public function encryptsMetadata(): bool
    {
        return $this->encryptMetadata;
    }

    public function streamsEncrypted(): bool
    {
        return $this->streamMethod !== 'None';
    }

    private function decrypt(string $method, string $data, int $num, int $gen): string
    {
        if ($method === 'None' || $data === '') {
            return $data;
        }
        if ($method === 'AESV3') {
            return self::aesCbc($this->fileKey, $data, 'aes-256-cbc');
        }
        // R≤4 object key (Algorithm 1)
        $salt = $method === 'AESV2' ? 'sAlT' : '';
        $objKey = md5($this->fileKey . substr(pack('V', $num), 0, 3) . substr(pack('V', $gen), 0, 2) . $salt, true);
        $objKey = substr($objKey, 0, min(\strlen($this->fileKey) + 5, 16));
        return $method === 'AESV2' ? self::aesCbc($objKey, $data, 'aes-128-cbc') : self::rc4($objKey, $data);
    }

    /** AES-CBC with the IV in the first 16 bytes and PKCS#7 padding. */
    private static function aesCbc(string $key, string $data, string $cipher): string
    {
        if (\strlen($data) < 32 || \strlen($data) % 16 !== 0) {
            return ''; // too short/garbled: treat as empty (what viewers do)
        }
        $plain = openssl_decrypt(substr($data, 16), $cipher, $key, OPENSSL_RAW_DATA, substr($data, 0, 16));
        return $plain === false ? '' : $plain;
    }

    private static function aesNoIv(string $key, string $data): string
    {
        $out = openssl_decrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 16));
        if ($out === false) {
            throw new EncryptionException('AES is not available on this server.', 'engine_failed');
        }
        return $out;
    }

    /** RC4 stream cipher (needed for R2–R4; removed from OpenSSL 3's default provider). */
    public static function rc4(string $key, string $data): string
    {
        $s = range(0, 255);
        $keyLength = \strlen($key);
        for ($i = 0, $j = 0; $i < 256; $i++) {
            $j = ($j + $s[$i] + \ord($key[$i % $keyLength])) & 0xFF;
            [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
        }
        $out = '';
        $length = \strlen($data);
        for ($i = 0, $j = 0, $k = 0; $k < $length; $k++) {
            $i = ($i + 1) & 0xFF;
            $j = ($j + $s[$i]) & 0xFF;
            [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
            $out .= \chr(\ord($data[$k]) ^ $s[($s[$i] + $s[$j]) & 0xFF]);
        }
        return $out;
    }

    /* ========================================================= helpers */

    private function cryptFilterMethod(?PdfType $cf, ?PdfType $name, callable $resolve): string
    {
        $filterName = $name instanceof PdfName ? $name->value : 'Identity';
        if ($filterName === 'Identity') {
            return 'None';
        }
        $entry = $cf instanceof PdfDictionary && isset($cf->value[$filterName]) ? $resolve($cf->value[$filterName]) : null;
        $cfm = $entry instanceof PdfDictionary && isset($entry->value['CFM']) ? $resolve($entry->value['CFM']) : null;
        return match ($cfm instanceof PdfName ? $cfm->value : 'None') {
            'V2' => 'RC4',
            'AESV2' => 'AESV2',
            'AESV3' => 'AESV3',
            'None' => 'None',
            default => throw new EncryptionException('This PDF uses an unsupported crypt filter.', 'unsupported_encryption'),
        };
    }

    private static function xorKey(string $key, int $i): string
    {
        $out = '';
        for ($k = 0, $n = \strlen($key); $k < $n; $k++) {
            $out .= \chr(\ord($key[$k]) ^ $i);
        }
        return $out;
    }

    /** Raw bytes of a PDF string or hex string. */
    public static function bytes(?PdfType $value): string
    {
        if ($value instanceof PdfHexString) {
            return (string) hex2bin(\strlen($value->value) % 2 ? $value->value . '0' : $value->value);
        }
        if ($value instanceof PdfString) {
            return PdfString::unescape($value->value);
        }
        return '';
    }

    private static function num(?PdfType $value, int $default): int
    {
        return $value instanceof PdfNumeric ? (int) $value->value : $default;
    }
}
