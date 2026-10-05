<?php

declare(strict_types=1);

namespace App\Services\FinancialIntelligence;

use Smalot\PdfParser\Config;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;

/** Native text extraction under memory and line bounds. Images are discarded; nothing is executed. */
final class PdfStatementText
{
    public function parse(string $bytes): Document
    {
        $config = new Config;
        $config->setRetainImageContent(false);
        $config->setIgnoreEncryption(false);
        $config->setDecodeMemoryLimit((int) config('financial_intelligence.statement_pdf_decode_limit_bytes'));

        return (new Parser([], $config))->parseContent($bytes);
    }

    /** @return list<string> trimmed, non-empty text lines in page order */
    public function lines(Document $pdf): array
    {
        $limit = (int) config('financial_intelligence.statement_pdf_max_lines');
        $lines = [];
        foreach ($pdf->getPages() as $page) {
            foreach (preg_split('/\R/u', $page->getText()) ?: [] as $line) {
                $line = trim(preg_replace('/[^\P{C}\t]/u', '', $line) ?? '');
                if ($line === '') {
                    continue;
                }
                $lines[] = preg_replace('/\s+/u', ' ', $line) ?? $line;
                if (count($lines) > $limit) {
                    throw new \LengthException('The PDF exceeds the statement line limit.');
                }
            }
        }

        return $lines;
    }
}
