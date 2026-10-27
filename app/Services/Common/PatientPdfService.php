<?php

namespace App\Services\Common;

/**
 * Small server-owned PDF renderer for patient-safe documents.
 * It deliberately accepts normalized data arrays only; no client HTML is parsed.
 */
final class PatientPdfService
{
    private const WIDTH = 595;
    private const HEIGHT = 842;
    private const LEFT = 42;
    private const RIGHT = 553;

    /**
     * Render the pharmacy invoice using the same canonical sections as the
     * browser invoice: parties, medicine table, payment ledger, and totals.
     */
    public function generatePharmacyInvoice(array $invoice): string
    {
        $currency = (string) ($invoice['currency'] ?? 'INR');
        $money = static fn ($value): string => $currency . ' ' . number_format((float) $value, 2);
        $pages = [];
        $page = [];
        $y = 42;
        $teal = [35, 78, 92];
        $gold = [195, 165, 109];
        $muted = [96, 114, 122];

        $this->text($page, self::LEFT, $y, 'USHA DERMA', 18, true, $teal);
        $this->text($page, 390, $y, 'INVOICE', 10, true, $muted);
        $this->text($page, 390, $y + 18, (string) ($invoice['invoice_number'] ?? 'Not recorded'), 12, true, $teal);
        $this->text($page, 390, $y + 32, 'Invoice date: ' . (string) ($invoice['invoice_created_at'] ?? $invoice['created_at'] ?? 'Not recorded'), 8, false, $muted);
        $this->text($page, self::LEFT, $y + 22, 'Pharmacy invoice', 8, false, $muted);
        $this->line($page, self::LEFT, $y + 46, self::RIGHT, $y + 46, $gold);
        $y += 62;

        $this->box($page, self::LEFT, $y, self::RIGHT, 72, [245, 248, 248], [224, 232, 233]);
        $partyX = [52, 178, 306, 434];
        $partyData = [
            ['PHARMACY', (string) ($invoice['clinic_name'] ?? 'USHA DERMA Clinic'), 'USHA DERMA Pharmacy'],
            ['PATIENT / CUSTOMER', (string) ($invoice['patient_name'] ?? 'Not recorded'), (string) ($invoice['patient_number'] ?? 'Not recorded')],
            ['ORDER NUMBER', (string) ($invoice['order_number'] ?? 'Not recorded'), 'Currency: ' . $currency],
            ['PAYMENT STATUS', (string) ($invoice['payment_status_label'] ?? $invoice['payment_status'] ?? 'Unpaid'), 'Reference: ' . (string) ($invoice['payment_reference'] ?? 'Not recorded')],
        ];
        foreach ($partyData as $index => $party) {
            $this->text($page, $partyX[$index], $y + 17, $party[0], 7, true, $muted);
            foreach ($this->wrap($party[1], 21) as $lineIndex => $line) {
                $this->text($page, $partyX[$index], $y + 32 + ($lineIndex * 10), $line, 8, $lineIndex === 0, $teal);
            }
            $this->text($page, $partyX[$index], $y + 57, $party[2], 7, false, $muted);
        }
        $y += 88;

        $this->box($page, self::LEFT, $y, self::RIGHT, 24, $teal, $teal);
        $this->text($page, 52, $y + 16, 'MEDICINE', 7, true, [255, 255, 255]);
        $this->text($page, 390, $y + 16, 'QTY', 7, true, [255, 255, 255]);
        $this->text($page, 450, $y + 16, 'RATE', 7, true, [255, 255, 255]);
        $this->text($page, 505, $y + 16, 'AMOUNT', 7, true, [255, 255, 255]);
        $y += 24;
        foreach (($invoice['items'] ?? []) as $item) {
            $nameLines = $this->wrap((string) ($item['description'] ?? $item['medicine_name'] ?? 'Medicine'), 50);
            $height = max(25, count($nameLines) * 10 + 12);
            if ($y + $height > 730) {
                $this->footer($page);
                $pages[] = $page;
                $page = [];
                $y = 42;
                $this->text($page, self::LEFT, $y, 'USHA DERMA', 14, true, $teal);
                $this->text($page, 390, $y, 'INVOICE (continued)', 9, true, $muted);
                $y += 28;
            }
            $this->box($page, self::LEFT, $y, self::RIGHT, $height, [255, 255, 255], [228, 235, 236]);
            foreach ($nameLines as $lineIndex => $line) $this->text($page, 52, $y + 16 + ($lineIndex * 10), $line, 8, $lineIndex === 0, [38, 52, 58]);
            $this->text($page, 390, $y + 16, number_format((float) ($item['quantity'] ?? 0), 2), 8, false, [38, 52, 58]);
            $this->text($page, 450, $y + 16, $money($item['unit_price'] ?? 0), 8, false, [38, 52, 58]);
            $this->text($page, 505, $y + 16, $money($item['line_total'] ?? 0), 8, true, [38, 52, 58]);
            $y += $height;
        }
        if (($invoice['items'] ?? []) === []) {
            $this->box($page, self::LEFT, $y, self::RIGHT, 26, [255, 255, 255], [228, 235, 236]);
            $this->text($page, 52, $y + 16, 'No medicine items recorded.', 8, false, $muted);
            $y += 26;
        }
        $y += 24;

        $this->text($page, self::LEFT, $y, 'PAYMENT HISTORY', 9, true, $teal);
        $ledgerY = $y + 16;
        foreach (($invoice['payment_history'] ?? []) as $payment) {
            if ($ledgerY > 755) break;
            $this->text($page, self::LEFT, $ledgerY, (string) ($payment['paid_at'] ?? 'Not recorded'), 7, false, $muted);
            $this->text($page, self::LEFT, $ledgerY + 11, (string) ($payment['payment_method'] ?? 'Not recorded') . ' | ' . (string) ($payment['reference_number'] ?? 'No reference'), 7, false, [38, 52, 58]);
            $this->text($page, 220, $ledgerY + 6, $money($payment['amount'] ?? 0), 8, true, $teal);
            $ledgerY += 30;
        }
        if (($invoice['payment_history'] ?? []) === []) $this->text($page, self::LEFT, $ledgerY, 'No payments recorded.', 8, false, $muted);

        $totalY = $y - 4;
        $this->box($page, 350, $totalY, self::RIGHT, 168, [251, 252, 252], [217, 227, 229]);
        $totalRows = [['Medicine subtotal', $money($invoice['subtotal'] ?? 0)], ['Packaging charges', $money($invoice['tax'] ?? 0)], ['Discount', '-' . $money($invoice['discount'] ?? 0)], ['Grand total', $money($invoice['amount'] ?? 0)], ['Paid amount', $money($invoice['paid_amount'] ?? 0)], ['Remaining balance', $money($invoice['remaining_balance'] ?? 0)]];
        foreach ($totalRows as $index => [$label, $value]) {
            $rowY = $totalY + 25 + ($index * 25);
            if ($index === 3) {
                $this->box($page, 350, $rowY - 17, self::RIGHT, 25, $teal, $teal);
                $this->text($page, 362, $rowY, $label, 8, true, [255, 255, 255]);
                $this->text($page, 465, $rowY, $value, 8, true, [255, 255, 255]);
            } else {
                $this->text($page, 362, $rowY, $label, 8, $index === 0, $muted);
                $this->text($page, 465, $rowY, $value, 8, true, $teal);
            }
        }
        $this->footer($page);
        $pages[] = $page;

        return $this->build($pages);
    }

    public function generate(string $title, string $reference, array $clinic, array $patient, array $sections): string
    {
        $pages = [];
        $page = [];
        $y = 48;
        $this->header($page, $clinic, $title, $reference, $y);

        foreach ($sections as $section) {
            $rows = array_values(array_filter($section['rows'] ?? [], static fn (array $row): bool => trim((string) ($row[1] ?? '')) !== ''));

            if ($rows === []) {
                continue;
            }

            if ($y > 735) {
                $this->footer($page);
                $pages[] = $page;
                $page = [];
                $y = 48;
                $this->header($page, $clinic, $title . ' (continued)', $reference, $y);
            }

            $this->text($page, self::LEFT, $y, (string) ($section['title'] ?? 'Details'), 10, true, [31, 78, 121]);
            $y += 16;

            foreach ($rows as $row) {
                $label = strtoupper(trim((string) ($row[0] ?? '')));
                $valueLines = $this->wrap((string) ($row[1] ?? ''), 82);
                $height = max(27, count($valueLines) * 12 + 17);

                if ($y + $height > 770) {
                    $this->footer($page);
                    $pages[] = $page;
                    $page = [];
                    $y = 48;
                    $this->header($page, $clinic, $title . ' (continued)', $reference, $y);
                }

                $this->box($page, self::LEFT, $y, self::RIGHT, $height, [255, 255, 255], [226, 231, 236]);
                $this->text($page, self::LEFT + 10, $y + 12, $label, 7, true, [100, 108, 120]);
                foreach ($valueLines as $index => $line) {
                    $this->text($page, self::LEFT + 150, $y + 12 + $index * 12, $line, 8, $index === 0);
                }
                $y += $height + 5;
            }
            $y += 5;
        }

        $this->footer($page);
        $pages[] = $page;

        return $this->build($pages);
    }

    private function header(array &$page, array $clinic, string $title, string $reference, int &$y): void
    {
        $this->text($page, self::LEFT, $y, 'USHA DERMA', 18, true, [31, 78, 121]);
        $this->text($page, 390, $y, $title, 12, true, [31, 78, 121]);
        $y += 18;
        $this->text($page, self::LEFT, $y, (string) ($clinic['clinic_name'] ?? 'USHA DERMA Clinic'), 10, true);
        $this->text($page, 390, $y, $reference, 8, false, [80, 88, 100]);
        $y += 14;
        $clinicLine = implode(' | ', array_filter([(string) ($clinic['address'] ?? ''), (string) ($clinic['mobile'] ?? ''), (string) ($clinic['email'] ?? '')]));
        $this->text($page, self::LEFT, $y, $clinicLine !== '' ? $clinicLine : 'Clinic contact not recorded', 7, false, [90, 96, 106]);
        $y += 16;
        $patientLine = trim(implode(' | ', array_filter([(string) ($patient['name'] ?? ''), (string) ($patient['registration_number'] ?? '')])));
        $this->box($page, self::LEFT, $y, self::RIGHT, 34, [246, 248, 250], [226, 231, 236]);
        $this->text($page, self::LEFT + 10, $y + 14, 'PATIENT', 7, true, [100, 108, 120]);
        $this->text($page, self::LEFT + 150, $y + 14, $patientLine !== '' ? $patientLine : 'Patient', 9, true);
        $y += 47;
    }

    private function footer(array &$page): void
    {
        $this->line($page, self::LEFT, 790, self::RIGHT, 790, [210, 210, 210]);
        $this->text($page, self::LEFT, 808, 'Generated by USHA DERMA', 7, false, [110, 110, 110]);
        $this->text($page, 455, 808, 'Patient copy', 7, false, [110, 110, 110]);
    }

    private function wrap(string $value, int $width): array
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?: 'Not recorded';
        $words = preg_split('/\s+/u', $value) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            if ($line !== '' && mb_strlen($line . ' ' . $word) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $line === '' ? $word : $line . ' ' . $word;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: ['Not recorded'];
    }

    private function text(array &$page, int $x, int $y, string $value, int $size, bool $bold = false, array $color = [35, 35, 35]): void
    {
        $font = $bold ? 'F2' : 'F1';
        $page[] = sprintf('BT /%s %d Tf %.3F %.3F %.3F rg 1 0 0 1 %d %d Tm (%s) Tj ET', $font, $size, $color[0] / 255, $color[1] / 255, $color[2] / 255, $x, self::HEIGHT - $y, $this->escape($value));
    }

    private function box(array &$page, int $x1, int $y, int $x2, int $height, array $fill, array $stroke): void
    {
        $page[] = sprintf('%.3F %.3F %.3F rg %.3F %.3F %.3F RG %d %d %d %d re B', $fill[0] / 255, $fill[1] / 255, $fill[2] / 255, $stroke[0] / 255, $stroke[1] / 255, $stroke[2] / 255, $x1, self::HEIGHT - $y - $height, $x2 - $x1, $height);
    }

    private function line(array &$page, int $x1, int $y1, int $x2, int $y2, array $color): void
    {
        $page[] = sprintf('%.3F %.3F %.3F RG %d %d m %d %d l S', $color[0] / 255, $color[1] / 255, $color[2] / 255, $x1, self::HEIGHT - $y1, $x2, self::HEIGHT - $y2);
    }

    private function escape(string $value): string
    {
        $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $value);
    }

    private function build(array $pages): string
    {
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', 'PLACEHOLDER', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>'];
        $kids = [];

        foreach ($pages as $commands) {
            $contentObject = count($objects) + 1;
            $content = implode("\n", $commands);
            $objects[] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            $pageObject = count($objects) + 1;
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::WIDTH . ' ' . self::HEIGHT . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';
            $kids[] = $pageObject . ' 0 R';
        }

        $objects[1] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        $pdf = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $number + 1 . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

        return $pdf;
    }
}
