<?php

declare(strict_types=1);

namespace App\Services\FinancialIntelligence;

use Smalot\PdfParser\Document;
use Throwable;

/**
 * Structural screening of untrusted statement PDFs. Rejections protect the parser; signals are
 * evidence for human review. Neither is a finding that a document or customer is fraudulent.
 */
final class PdfStatementInspector
{
    public const PARSE = 'parse';

    public const ACTIVE_CONTENT = 'rejected_active_content';

    public const ENCRYPTED = 'export_required_password_protected';

    public const LIMITS = 'rejected_processing_limits';

    public const UNREADABLE = 'unreadable';

    /** Dictionary keys and action types that execute, launch, submit or embed content. */
    private const ACTIVE_KEYS = ['JavaScript', 'JS', 'Launch', 'EmbeddedFile', 'EmbeddedFiles', 'RichMedia', 'XFA', 'SubmitForm', 'ImportData', 'GoToR', 'GoToE'];

    private const EDITING_SOFTWARE = '/(ilovepdf|smallpdf|sejda|pdfescape|pdf-xchange|phantompdf|nitro pro|pdfelement|pdffiller|libreoffice|microsoft.{0,12}word|inkscape|canva|photoshop|gimp)/i';

    /** Checks run on the raw bytes before the parser sees the document. */
    public function screen(string $bytes): array
    {
        if (! str_starts_with($bytes, '%PDF-')) {
            return ['verdict' => self::UNREADABLE, 'signals' => []];
        }
        if (strlen($bytes) > (int) config('financial_intelligence.statement_pdf_max_bytes')) {
            return ['verdict' => self::LIMITS, 'signals' => []];
        }
        $names = $this->decodeNames($bytes);
        if (preg_match('#/Encrypt(?![A-Za-z0-9])#', $names) === 1) {
            return ['verdict' => self::ENCRYPTED, 'signals' => []];
        }
        if (preg_match('#/('.implode('|', self::ACTIVE_KEYS).')(?![A-Za-z0-9])#', $names) === 1) {
            return ['verdict' => self::ACTIVE_CONTENT, 'signals' => []];
        }
        $signals = [];
        if (substr_count($bytes, '%%EOF') > 1) {
            $signals[] = 'incremental_updates_present';
        }
        if (str_contains($bytes, '/ByteRange') && preg_match('#/(Sig|adbe\.pkcs7|ETSI\.CAdES)#', $bytes) === 1) {
            $signals[] = 'digital_signature_present_unvalidated';
        }

        return ['verdict' => self::PARSE, 'signals' => $signals];
    }

    /** Checks on the parsed object graph, which also covers dictionaries hidden in compressed object streams. */
    public function document(Document $pdf): array
    {
        $pages = count($pdf->getPages());
        if ($pages === 0) {
            return ['verdict' => self::UNREADABLE, 'signals' => [], 'metadata' => []];
        }
        if ($pages > (int) config('financial_intelligence.statement_pdf_max_pages')) {
            return ['verdict' => self::LIMITS, 'signals' => [], 'metadata' => []];
        }
        foreach ($pdf->getObjects() as $object) {
            $header = $object->getHeader();
            if ($header === null) {
                continue;
            }
            $keys = array_keys($header->getElementTypes());
            if (array_intersect($keys, self::ACTIVE_KEYS) !== []
                || in_array($this->name($header->has('S') ? $header->get('S') : null), self::ACTIVE_KEYS, true)
                || $this->name($header->has('Type') ? $header->get('Type') : null) === 'EmbeddedFile') {
                return ['verdict' => self::ACTIVE_CONTENT, 'signals' => [], 'metadata' => []];
            }
        }
        $details = $pdf->getDetails();
        $producer = $this->text($details['Producer'] ?? null);
        $creator = $this->text($details['Creator'] ?? null);
        $signals = [];
        if (preg_match(self::EDITING_SOFTWARE, $producer.' '.$creator) === 1) {
            $signals[] = 'editing_software_metadata';
        }
        $created = $this->time($details['CreationDate'] ?? null);
        $modified = $this->time($details['ModDate'] ?? null);
        if ($created !== null && $modified !== null && $modified - $created > 120) {
            $signals[] = 'modified_after_creation';
        }

        return ['verdict' => self::PARSE, 'signals' => $signals,
            'metadata' => ['pages' => $pages, 'producer' => $producer, 'creator' => $creator]];
    }

    /** Expands #xx escapes inside PDF names, so /Java#53cript is screened as /JavaScript. */
    private function decodeNames(string $bytes): string
    {
        return preg_replace_callback(
            '~/[^\s()<>\[\]{}/%]*#[0-9A-Fa-f]{2}[^\s()<>\[\]{}/%]*~',
            fn (array $name): string => preg_replace_callback('/#([0-9A-Fa-f]{2})/', fn (array $hex): string => chr((int) hexdec($hex[1])), $name[0]) ?? $name[0],
            $bytes,
        ) ?? $bytes;
    }

    private function name(mixed $element): ?string
    {
        try {
            return is_object($element) && method_exists($element, 'getContent') ? (string) $element->getContent() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? mb_substr(trim((string) $value), 0, 120) : '';
    }

    private function time(mixed $value): ?int
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        $time = strtotime($value);

        return $time === false ? null : $time;
    }
}
