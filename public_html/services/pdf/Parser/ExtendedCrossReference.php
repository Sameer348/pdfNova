<?php
/**
 * PDFNova — FPDI cross-reference resolver with PDF 1.5+ support.
 *
 * Adds to FPDI's free CrossReference:
 *   1. Cross-reference STREAMS (instead of only classic "xref" tables)
 *   2. Hybrid-reference files (classic table + /XRefStm hidden stream)
 *   3. Objects stored inside OBJECT STREAMS (/Type /ObjStm)
 *
 * Lookup order follows the PDF spec: newest revision first; within a
 * hybrid revision the table is consulted before its /XRefStm stream.
 *
 * Encrypted PDFs are still rejected (CrossReferenceException::ENCRYPTED).
 *
 * Original code for PDFNova. Extends FPDI (MIT licence).
 */

declare(strict_types=1);

namespace PDFNova\Pdf\Parser;

use setasign\Fpdi\PdfParser\CrossReference\CrossReference;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\CrossReference\FixedReader;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfHexString;
use setasign\Fpdi\PdfParser\Type\PdfString;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObject;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNull;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfTypeException;

class ExtendedCrossReference extends CrossReference
{
    /** Max revisions followed via /Prev (guards against malicious loops). */
    private const MAX_SECTIONS = 512;

    /** @var array<int, array{parser: PdfParser, first: int, map: array<int,int>}> decoded object streams */
    private array $objectStreams = [];

    /** Object streams currently being opened (detects self-referencing streams). */
    private array $opening = [];

    /** Decryption (only when the parser was given a password). */
    private bool $allowEncrypted = false;
    private ?PdfType $encryptEntry = null;
    private ?StandardSecurityHandler $security = null;
    private ?int $encryptObjectNumber = null;

    /**
     * Mirrors FPDI's constructor, adding /XRefStm (hybrid file) handling.
     */
    public function __construct(PdfParser $parser, $fileHeaderOffset = 0, bool $allowEncrypted = false)
    {
        $this->allowEncrypted = $allowEncrypted;
        $parser->getTokenizer()->clearStack();
        $this->parser = $parser;
        $this->fileHeaderOffset = $fileHeaderOffset;

        $offset = $this->findStartXref();
        $visited = [$offset];
        $reader = null;

        /** @noinspection TypeUnsafeComparisonInspection */
        while ($offset != false) {
            if (\count($this->readers) > self::MAX_SECTIONS) {
                throw new CrossReferenceException('Too many cross-reference sections.', CrossReferenceException::INVALID_DATA);
            }
            try {
                $reader = $this->readXref($offset + $this->fileHeaderOffset);
            } catch (CrossReferenceException $e) {
                if ($e->getCode() === CrossReferenceException::INVALID_DATA && $this->fileHeaderOffset !== 0) {
                    $this->fileHeaderOffset = 0;
                    $reader = $this->readXref($offset);
                } else {
                    throw $e;
                }
            }

            $trailer = $reader->getTrailer();
            $this->checkForEncryption($trailer);
            $this->readers[] = $reader;

            // Hybrid-reference file: compressed objects are listed in a hidden xref stream.
            $xrefStm = PdfDictionary::get($trailer, 'XRefStm');
            if ($xrefStm instanceof PdfNumeric && !\in_array($xrefStm->value, $visited, true)) {
                $visited[] = $xrefStm->value;
                try {
                    $this->readers[] = $this->readXref($xrefStm->value + $this->fileHeaderOffset);
                } catch (CrossReferenceException $e) {
                    // A broken hidden stream must not make the whole file unreadable.
                }
            }

            $prev = PdfDictionary::get($trailer, 'Prev');
            if (!$prev instanceof PdfNumeric) {
                break;
            }
            $offset = $prev->value;
            if (\in_array($offset, $visited, true)) {
                throw new CrossReferenceException('Cross-references includes cyclic structure.', CrossReferenceException::CYCLIC_STRUCTURE);
            }
            $visited[] = $offset;
        }

        if ($reader instanceof FixedReader) {
            $reader->fixFaultySubSectionShift();
        }
        if ($reader === null) {
            throw new CrossReferenceException('No cross-reference found.', CrossReferenceException::NO_XREF_FOUND);
        }
    }

    /**
     * Without a password: reject encrypted files (FPDI's behaviour).
     * With a password: remember the newest /Encrypt entry for the parser.
     */
    protected function checkForEncryption(PdfDictionary $dictionary)
    {
        if (!isset($dictionary->value['Encrypt'])) {
            return;
        }
        if (!$this->allowEncrypted) {
            parent::checkForEncryption($dictionary);
        }
        $this->encryptEntry ??= $dictionary->value['Encrypt'];
    }

    public function encryptEntry(): ?PdfType
    {
        return $this->encryptEntry;
    }

    public function setSecurity(StandardSecurityHandler $security, ?int $encryptObjectNumber): void
    {
        $this->security = $security;
        $this->encryptObjectNumber = $encryptObjectNumber;
    }

    /** Read an object WITHOUT decrypting it (for the /Encrypt dictionary and /ID). */
    public function getRawIndirectObject(int $objectNumber): PdfIndirectObject
    {
        $security = $this->security;
        $this->security = null;
        try {
            return $this->getIndirectObject($objectNumber);
        } finally {
            $this->security = $security;
        }
    }

    /**
     * Recognise cross-reference streams; defer to FPDI for classic tables.
     */
    protected function initReaderInstance($initValue)
    {
        if ($initValue instanceof PdfIndirectObject && $initValue->value instanceof PdfStream) {
            $type = PdfDictionary::get($initValue->value->value, 'Type');
            if ($type instanceof PdfName && $type->value === 'XRef') {
                $this->checkForEncryption($initValue->value->value);
                return new XrefStreamReader($initValue->value);
            }
        }
        return parent::initReaderInstance($initValue);
    }

    public function getIndirectObject($objectNumber)
    {
        $objectNumber = (int) $objectNumber;

        foreach ($this->readers as $reader) {
            if ($reader instanceof XrefStreamReader) {
                $entry = $reader->getEntry($objectNumber);
                if ($entry === null) {
                    continue;
                }
                [$type, $field2] = $entry;
                if ($type === 1) {
                    return $this->readObjectAt($objectNumber, $field2);
                }
                if ($type === 2) {
                    return $this->readCompressedObject($objectNumber, $field2);
                }
                // Type 0 (free) or unknown types: the object is null by definition.
                return PdfIndirectObject::create($objectNumber, 0, new PdfNull());
            }

            $offset = $reader->getOffsetFor($objectNumber);
            if ($offset !== false) {
                return $this->readObjectAt($objectNumber, $offset);
            }
        }

        throw new CrossReferenceException(
            \sprintf('Object (id:%s) not found.', $objectNumber),
            CrossReferenceException::OBJECT_NOT_FOUND
        );
    }

    /** Same as FPDI's own lookup for an object at a byte offset. */
    private function readObjectAt(int $objectNumber, int $offset): PdfIndirectObject
    {
        $parser = $this->parser;
        $parser->getTokenizer()->clearStack();
        $parser->getStreamReader()->reset($offset + $this->fileHeaderOffset);

        try {
            $object = $parser->readValue(null, PdfIndirectObject::class);
        } catch (PdfTypeException $e) {
            throw new CrossReferenceException(
                \sprintf('Object (id:%s) not found at location (%s).', $objectNumber, $offset),
                CrossReferenceException::OBJECT_NOT_FOUND,
                $e
            );
        }
        if (!$object instanceof PdfIndirectObject || $object->objectNumber !== $objectNumber) {
            throw new CrossReferenceException(
                \sprintf('Wrong object found while %s was expected.', $objectNumber),
                CrossReferenceException::OBJECT_NOT_FOUND
            );
        }
        if ($this->security !== null && $objectNumber !== $this->encryptObjectNumber) {
            $object = PdfIndirectObject::create(
                $objectNumber,
                $object->generationNumber,
                $this->decryptValue($object->value, $objectNumber, $object->generationNumber)
            );
        }
        return $object;
    }

    /**
     * Decrypt every string and stream inside an object. Objects stored in
     * object streams are NOT passed here: their container is decrypted instead.
     */
    private function decryptValue(PdfType $value, int $num, int $gen): PdfType
    {
        if ($value instanceof PdfString) {
            return PdfString::create(PdfString::escape($this->security->decryptString(PdfString::unescape($value->value), $num, $gen)));
        }
        if ($value instanceof PdfHexString) {
            $raw = StandardSecurityHandler::bytes($value);
            return PdfHexString::create(bin2hex($this->security->decryptString($raw, $num, $gen)));
        }
        if ($value instanceof PdfArray) {
            return PdfArray::create(array_map(fn(PdfType $v): PdfType => $this->decryptValue($v, $num, $gen), $value->value));
        }
        if ($value instanceof PdfStream) {
            $dict = $value->value;
            $type = PdfDictionary::get($dict, 'Type');
            $typeName = $type instanceof PdfName ? $type->value : '';
            $data = $value->getStream();
            $skip = $typeName === 'XRef'
                || !$this->security->streamsEncrypted()
                || ($typeName === 'Metadata' && !$this->security->encryptsMetadata())
                || $this->hasIdentityCryptFilter($dict);
            $newDict = $this->decryptValue($dict, $num, $gen);
            if (!$skip) {
                $data = $this->security->decryptStream($data, $num, $gen);
            }
            $newDict->value['Length'] = PdfNumeric::create(\strlen($data));
            return PdfStream::create($newDict, $data);
        }
        if ($value instanceof PdfDictionary) {
            $entries = [];
            foreach ($value->value as $key => $v) {
                $entries[$key] = $this->decryptValue($v, $num, $gen);
            }
            return PdfDictionary::create($entries);
        }
        return $value; // numbers, names, booleans, null, references
    }

    /** Stream with an explicit /Crypt filter set to Identity → stored unencrypted. */
    private function hasIdentityCryptFilter(PdfDictionary $dict): bool
    {
        $filter = PdfDictionary::get($dict, 'Filter');
        $filters = $filter instanceof PdfArray ? $filter->value : [$filter];
        foreach ($filters as $f) {
            if ($f instanceof PdfName && $f->value === 'Crypt') {
                return true; // only the Identity crypt filter is allowed on its own in practice
            }
        }
        return false;
    }

    /**
     * Read an object stored in an object stream. The stream starts with N
     * pairs "objectNumber byteOffset"; offsets are relative to /First.
     */
    private function readCompressedObject(int $objectNumber, int $streamNumber): PdfIndirectObject
    {
        if (!isset($this->objectStreams[$streamNumber])) {
            if (isset($this->opening[$streamNumber])) {
                throw new CrossReferenceException('Object stream refers to itself.', CrossReferenceException::CYCLIC_STRUCTURE);
            }
            $this->opening[$streamNumber] = true;
            try {
                $this->objectStreams[$streamNumber] = $this->openObjectStream($streamNumber);
            } finally {
                unset($this->opening[$streamNumber]);
            }
        }

        $os = $this->objectStreams[$streamNumber];
        if (!isset($os['map'][$objectNumber])) {
            throw new CrossReferenceException(
                \sprintf('Object (id:%s) not found in object stream %s.', $objectNumber, $streamNumber),
                CrossReferenceException::OBJECT_NOT_FOUND
            );
        }

        $parser = $os['parser'];
        $parser->getTokenizer()->clearStack();
        $parser->getStreamReader()->reset($os['first'] + $os['map'][$objectNumber]);
        $value = $parser->readValue();
        if ($value === false) {
            throw new CrossReferenceException(
                \sprintf('Object (id:%s) could not be read from its object stream.', $objectNumber),
                CrossReferenceException::OBJECT_NOT_FOUND
            );
        }
        return PdfIndirectObject::create($objectNumber, 0, $value);
    }

    /** @return array{parser: PdfParser, first: int, map: array<int,int>} */
    private function openObjectStream(int $streamNumber): array
    {
        $container = $this->getIndirectObject($streamNumber);
        try {
            $stream = PdfStream::ensure($container->value);
        } catch (PdfTypeException $e) {
            throw new CrossReferenceException('Object stream is not a stream.', CrossReferenceException::INVALID_DATA, $e);
        }

        $count = PdfDictionary::get($stream->value, 'N');
        $first = PdfDictionary::get($stream->value, 'First');
        if (!$count instanceof PdfNumeric || !$first instanceof PdfNumeric || $first->value < 0) {
            throw new CrossReferenceException('Object stream is missing /N or /First.', CrossReferenceException::INVALID_DATA);
        }

        $data = StreamDecoder::decode($stream);
        $first = (int) $first->value;

        preg_match_all('/\d+/', substr($data, 0, $first), $numbers);
        $numbers = $numbers[0];
        $map = [];
        for ($i = 0, $n = min((int) $count->value, intdiv(\count($numbers), 2)); $i < $n; $i++) {
            $map[(int) $numbers[$i * 2]] = (int) $numbers[$i * 2 + 1];
        }

        return [
            'parser' => new PdfParser(StreamReader::createByString($data)),
            'first'  => $first,
            'map'    => $map,
        ];
    }
}
