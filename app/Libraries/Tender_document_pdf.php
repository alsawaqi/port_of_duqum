<?php

namespace App\Libraries;

/** Printable PODC documents. Letterheads are embedded locally, never fetched over HTTP. */
class Tender_document_pdf extends Pdf
{
    protected $tcpdflink = false;
    private string $recordReference = '';
    private array $repeatHeader = [];
    private array $repeatWidths = [];

    public function __construct()
    {
        foreach (['first', 'continuation'] as $variant) {
            $asset = FCPATH . 'assets/tender_documents/letterhead-' . $variant . '.png';
            $info = is_readable($asset) ? @getimagesize($asset) : false;
            if (!is_array($info) || ($info[2] ?? 0) !== IMAGETYPE_PNG) {
                throw new \RuntimeException('The tender document letterhead is missing or unreadable.');
            }
        }
        parent::__construct();
        $this->SetCreator('Port of Duqm');
        $this->SetAuthor('Port of Duqm Company S.A.O.C');
        $this->SetMargins(23, 31, 23);
        $this->SetAutoPageBreak(true, 32);
        $this->SetFont('dejavusans', '', 10);
        $this->setCellHeightRatio(1.3);
        $this->setCellPaddings(2, 2, 2, 2);
        $this->SetDrawColor(145, 151, 156);
        $this->SetLineWidth(0.2);
    }

    public function Header()
    {
        $margin = $this->getBreakMargin();
        $auto = $this->AutoPageBreak;
        $this->SetAutoPageBreak(false, 0);
        $variant = $this->getPage() === 1 ? 'first' : 'continuation';
        $this->Image(FCPATH . 'assets/tender_documents/letterhead-' . $variant . '.png', 0, 0, 210, 297, 'PNG');
        $this->SetAutoPageBreak($auto, $margin);
    }

    public function Footer()
    {
        if ($this->recordReference === '') {
            return;
        }
        $this->SetDrawColor(165, 145, 85);
        $this->Line(23, 273, 187, 273);
        $this->SetXY(23, 274);
        $this->SetTextColor(120, 120, 120);
        $this->SetFont('dejavusans', '', 7);
        $this->Cell(128, 4, 'Tender No. ' . $this->recordReference . ' | Bid Opening Record');
        $this->Cell(36, 4, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
    }

    public static function filename(string $prefix, string $reference): string
    {
        return $prefix . '-' . (substr(preg_replace('/[^A-Za-z0-9_-]+/', '-', $reference), 0, 90) ?: 'tender') . '.pdf';
    }

    /** Snapshot the complete letter in the existing communication message at award time. */
    public static function regretMessage(object $tender, object $vendor, string $issuedAt): string
    {
        $line = static fn($value) => trim(preg_replace('/[\r\n\t]+/u', ' ', (string) $value));
        $name = $line($vendor->vendor_name ?? 'Tenderer');
        $reference = $line($tender->reference ?? '');
        $submission = self::dateOnly($vendor->submitted_at ?? null);
        return "- Restricted -\n\nRef.: REGRET-" . $reference . '-V' . (int) $vendor->id
            . "\nDate: " . self::dateOnly($issuedAt)
            . "\n\n" . $name . "\n" . trim((string) ($vendor->address ?? ''))
            . "\nMobile: " . ($line($vendor->phone ?? '') ?: 'Not recorded')
            . "\nEmail: " . $line($vendor->email ?? '')
            . "\n\nAfter compliments,\n\nSUB: Letter to Unsuccessful Tenderer – Tender No. " . $reference . ' – ' . $line($tender->title ?? '')
            . "\n\nDear " . $name . ",\n\nWe write further to your tender submission dated " . $submission
            . '. Port of Duqm Company S.A.O.C has evaluated your tender, and regrets to inform you that on this occasion you have not been successful.'
            . "\n\nWe would however like to take this opportunity to thank you for the interest that you have shown in this requirement."
            . "\n\nYours sincerely,\n\n\n\nButhaina Al Zadjali";
    }

    private static function dateOnly($date): string
    {
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $date)) {
            return 'Not recorded';
        }
        // Tender timestamps are stored in the tender's business timezone already.
        return (new \DateTimeImmutable(substr((string) $date, 0, 10)))->format('d F Y');
    }

    public static function regret(object $letter): self
    {
        $pdf = new self();
        $pdf->SetTitle((string) ($letter->subject ?? 'Regret Letter'));
        $pdf->AddPage();
        foreach (explode("\n", str_replace("\r", '', (string) $letter->message)) as $line) {
            if ($line === '') {
                $pdf->Ln(3);
                continue;
            }
            $subject = str_starts_with($line, 'SUB:');
            $restricted = $line === '- Restricted -';
            $bold = $subject || str_starts_with($line, 'Ref.:') || str_starts_with($line, 'Date:') || $line === 'Buthaina Al Zadjali';
            $pdf->SetFont('dejavusans', $subject ? 'BU' : ($bold ? 'B' : ''), $restricted ? 9 : 10);
            $pdf->SetTextColor(...($subject ? [12, 112, 177] : ($restricted ? [130, 130, 130] : [40, 45, 50])));
            $pdf->MultiCell(164, 5, $line, 0, $restricted ? 'C' : 'L', false, 1, '', '', true, 0, false, false, 0, 'T', false);
        }
        return $pdf;
    }

    public static function opening(object $tender, object $session, array $bidders, array $signatures): self
    {
        // Defence in depth: a PDF must never become an alternative way to open sealed bids.
        if (!in_array((string) ($session->status ?? ''), ['unlocked', 'signed', 'manual_accepted'], true)) {
            throw new \DomainException('The bid opening must be unlocked before printing its record.');
        }
        $pdf = new self();
        $pdf->recordReference = (string) $tender->reference;
        $pdf->SetTitle('Bid Opening Record - ' . $pdf->recordReference);
        $pdf->AddPage();
        $pdf->heading('BID OPENING RECORD', 16);
        $pdf->SetDrawColor(165, 145, 85);
        $pdf->Line(23, $pdf->GetY(), 187, $pdf->GetY());
        $pdf->Ln(5);
        $openingAt = $session->unlocked_at ?? $tender->bid_opening_at ?? null;
        $currency = (string) (($tender->currency ?? '') ?: 'OMR');
        foreach ([
            'Tender Number' => (string) $tender->reference,
            'Tender Title' => (string) $tender->title,
            'Tender Budget' => self::money($tender->budget_omr ?? null) . ' OMR',
            'Opening Date' => self::dateOnly($openingAt),
            'Submission Date' => self::dateOnly($tender->closing_at ?? null),
            'Time & Place' => ($openingAt ? substr((string) $openingAt, 11, 5) . ' / ' : '') . 'PODC Tender Committee Meeting',
        ] as $label => $value) {
            $pdf->row([$label, $value], [41, 123], false, true);
        }
        $pdf->Ln(5);
        $pdf->heading('Bids Received');
        $pdf->tableHeader(['Sr. No.', 'Supplier', 'Total (' . $currency . ')', 'Comments'], [14, 81, 34, 35]);
        $count = 0;
        foreach ($bidders as $bidder) {
            if (empty($bidder->bid_id) || ($bidder->bid_status ?? '') === 'draft') {
                continue;
            }
            $amount = self::money($bidder->total_amount ?? null);
            if (!empty($bidder->currency) && $bidder->currency !== $currency) {
                $amount .= ' ' . $bidder->currency;
            }
            // Leave comments blank for the committee; do not invent findings or attendance.
            $pdf->row([(string) ++$count, (string) $bidder->vendor_name, $amount, ''], [14, 81, 34, 35]);
        }
        if (!$count) {
            $pdf->row(['', 'No bids received.', '', ''], [14, 81, 34, 35]);
        }
        $pdf->repeatHeader = [];
        $pdf->ensureSpace(40);
        $pdf->Ln(6);
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->MultiCell(164, 5, 'The bids were opened in the presence of the following ITC members:', 0, 'L');
        $pdf->Ln(2);
        $pdf->tableHeader(['Name', 'Title', 'Remarks', 'Signature'], [51, 37, 28, 48]);
        $memberCount = 0;
        foreach ($signatures as $signature) {
            if (empty($signature->is_valid) || (int) $signature->tender_bid_opening_id !== (int) $session->id) {
                continue;
            }
            $role = (string) $signature->role;
            $memberCount++;
            $name = trim((string) ($signature->signature_name ?? '')) ?: (string) ($signature->member_name ?? $signature->member_email ?? '');
            $signed = !empty($signature->signed_at);
            $pdf->row([$name, ['chairman' => 'Chairman of ITC', 'secretary' => 'Secretary', 'itc_member' => 'Member'][$role] ?? $role,
                $signed ? 'Signed digitally' : '', ''], [51, 37, 28, 48], false, false, 18);
            $bottom = $pdf->GetY();
            if ($signed) {
                $path = !empty($signature->signature_image_path) ? (new Upload_security())->resolveStoredFile(
                    (string) $signature->signature_image_path, 'tender_opening_signatures/opening_' . (int) $session->id
                ) : null;
                $image = $path ? self::signaturePng($path) : null;
                if ($image !== null) {
                    $pdf->Image('@' . $image, 141, $bottom - 16, 42, 12, 'PNG', '', '', true, 150, '', false, false, 0, 'CM', false, false);
                } else {
                    $pdf->SetFont('dejavusans', '', 8);
                    $pdf->MultiCell(44, 4, $name . "\n" . self::dateOnly($signature->signed_at), 0, 'C', false, 1, 141, $bottom - 16);
                }
                $pdf->SetXY(23, $bottom);
            }
        }
        if (!$memberCount) {
            for ($i = 0; $i < 3; $i++) {
                $pdf->row(['', '', '', ''], [51, 37, 28, 48], false, false, 18);
            }
        }
        $pdf->repeatHeader = [];
        $pdf->Ln(3);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->MultiCell(164, 5, 'By signing this Bid Opening Record, the ITC Members hereby declare that there is no conflict of interest involving the above bidders.', 0, 'L');
        return $pdf;
    }

    private static function money($amount): string
    {
        return $amount === null || $amount === '' ? 'Not recorded' : number_format((float) $amount, 3);
    }

    private static function signaturePng(string $path): ?string
    {
        $info = @getimagesize($path);
        if (!is_array($info) || $info[2] !== IMAGETYPE_PNG || !function_exists('imagecreatefrompng')
            || $info[0] * $info[1] > 4000000) {
            return null;
        }
        // Decode before TCPDF: a damaged legacy signature must not abort the whole print request.
        $source = @imagecreatefrompng($path);
        if ($source === false) {
            return null;
        }
        $flat = imagecreatetruecolor($info[0], $info[1]);
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $source, 0, 0, 0, 0, $info[0], $info[1]);
        ob_start();
        imagepng($flat);
        $bytes = ob_get_clean();
        imagedestroy($source);
        imagedestroy($flat);
        return $bytes ?: null;
    }

    private function heading(string $text, int $size = 12): void
    {
        $this->SetFont('dejavusans', 'B', $size);
        $this->SetTextColor(12, 112, 177);
        $this->MultiCell(164, 6, $text, 0, 'L');
        $this->SetTextColor(40, 45, 50);
    }

    private function ensureSpace(float $height): void
    {
        if ($this->GetY() + $height > 265) {
            $this->AddPage();
            if ($this->repeatHeader) {
                $this->row($this->repeatHeader, $this->repeatWidths, true);
            }
        }
    }

    private function tableHeader(array $cells, array $widths): void
    {
        $this->ensureSpace(20);
        $this->repeatHeader = $cells;
        $this->repeatWidths = $widths;
        $this->row($cells, $widths, true);
    }

    private function row(array $cells, array $widths, bool $header = false, bool $labels = false, float $minimum = 9.7): void
    {
        $this->SetFont('dejavusans', $header ? 'B' : '', 9);
        $height = $minimum;
        foreach ($cells as $i => $text) {
            $height = max($height, $this->getStringHeight($widths[$i], (string) $text));
        }
        if (!$header) {
            $this->ensureSpace($height);
        }
        $x = 23; $y = $this->GetY();
        $this->SetDrawColor(145, 151, 156);
        foreach ($cells as $i => $text) {
            $this->SetFont('dejavusans', ($header || ($labels && $i === 0)) ? 'B' : '', 9);
            $this->SetTextColor(...($header ? [255, 255, 255] : [40, 45, 50]));
            $this->SetFillColor(...($header ? [14, 112, 177] : [233, 242, 248]));
            $this->MultiCell($widths[$i], $height, (string) $text, 1, $header || $i === 2 ? 'C' : 'L', $header || ($labels && $i === 0), 0, $x, $y, true, 0, false, true, $height, 'M', true);
            $x += $widths[$i];
        }
        $this->SetTextColor(40, 45, 50);
        $this->SetXY(23, $y + $height);
    }
}
