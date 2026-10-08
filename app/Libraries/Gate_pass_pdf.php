<?php

namespace App\Libraries;

use App\Libraries\Payments\Payment_amount;

/** The approved Port of Duqm transcript layout. This class never grants pass eligibility. */
final class Gate_pass_pdf extends Pdf
{
    private array $overflow = [];
    private string $passNumber = '';
    private bool $continuation = false;
    private const ASSETS = FCPATH . 'assets/gate_pass/';

    public function __construct()
    {
        parent::__construct('');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->tcpdflink = false;
        $this->SetMargins(8, 50, 8);
        $this->SetAutoPageBreak(false, 0);
        $this->setCellPaddings(0, 0, 0, 0);
        $this->setCellHeightRatio(1.2);
        $this->SetCreator('Port of Duqm');
        $this->SetAuthor('Port of Duqm');
        $this->SetTitle('Online Gate Pass');
    }

    /**
     * Keep per-visitor passes scoped to their holder. Legacy request-level passes
     * can show companions; vehicles are recorded against the request, not a person.
     */
    public static function details(object $request, object $pass, array $visitors, array $vehicles, ?object $payment = null): array
    {
        $visitors = array_values(array_filter($visitors, static fn ($v) => empty($v->deleted)));
        $assignedId = (int) ($pass->gate_pass_request_visitor_id ?? 0);
        if ($assignedId) {
            $visitors = array_values(array_filter($visitors, static fn ($v) => (int) ($v->id ?? 0) === $assignedId));
            if (!$visitors) {
                throw new \DomainException('The assigned gate pass visitor is unavailable.');
            }
        } else {
            usort($visitors, static fn ($a, $b) => (int) ($b->is_primary ?? 0) <=> (int) ($a->is_primary ?? 0));
        }
        $holder = array_shift($visitors);
        $plates = [];
        if (strtolower((string) ($request->request_type ?? 'both')) !== 'person') {
            foreach ($vehicles as $vehicle) {
                if (empty($vehicle->deleted)) {
                    $plates[] = gate_pass_vehicle_plate_display($vehicle);
                }
            }
        }

        // Dates are calendar dates, not UTC instants (in particular visit_to at 23:59:59).
        $date = static function ($value): string {
            $value = substr(trim((string) $value), 0, 10);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) && $value !== '0000-00-00' ? $value : '-';
        };
        $paymentCells = ['Not recorded', '-', '-', '-'];
        if (!empty($request->fee_is_waived)) {
            $paymentCells[0] = 'Waived';
        } elseif (isset($request->fee_amount) && is_numeric($request->fee_amount) && (float) $request->fee_amount === 0.0) {
            $paymentCells[0] = 'No charge';
        } elseif ($payment && ($payment->subject_type ?? '') === 'gate_pass_fee'
            && (int) ($payment->subject_id ?? 0) === (int) $request->id
            && empty($payment->vendor_id) && empty($payment->deleted)
            && ($payment->provider ?? '') === 'bank_muscat' && ($payment->status ?? '') === 'paid'
            && !empty($payment->verified_at) && ($payment->settlement_status ?? '') === 'applied'
            && strtoupper((string) ($payment->currency ?? '')) === strtoupper((string) ($request->currency ?? 'OMR'))) {
            try {
                if (Payment_amount::toMinor((string) $payment->amount, 3) === Payment_amount::toMinor((string) $request->fee_amount, 3)) {
                    $paymentCells = [
                        strtoupper((string) $payment->currency) . ' ' . number_format((float) $payment->amount, 3, '.', ''),
                        (string) $payment->id,
                        (string) ($payment->provider_checkout_id ?? ''),
                        (string) (($payment->bank_reference ?? '') ?: ($payment->provider_payment_id ?? '')),
                    ];
                }
            } catch (\InvalidArgumentException $e) {
                // A missing/mismatched legacy ledger must not become a fabricated receipt.
            }
        }

        return [
            'name' => (string) ($holder->full_name ?? ''),
            'identity' => (string) ($holder->id_number ?? ''),
            'from' => $date($request->visit_from ?? ''),
            'to' => $date($request->visit_to ?? ''),
            'email' => (string) ($request->requester_email ?? ''),
            'phone' => trim((string) ($holder->phone ?? '')) ?: (string) ($request->requester_phone ?? ''),
            'plates' => implode(', ', $plates),
            'company' => (string) ($request->company_name ?? ''),
            // There is no escort or per-visitor vehicle/remarks field in the current schema.
            'escort' => '-',
            'purpose' => (string) ($request->purpose_name ?? ''),
            'requested' => $date($request->created_at ?? ''),
            'companions' => $visitors,
            'individual' => $assignedId > 0,
            'payment' => $paymentCells,
        ];
    }

    public function build(object $request, object $pass, array $visitors, array $vehicles, ?object $payment = null): self
    {
        foreach (['letterhead.jpg', 'hsse-instructions.png'] as $asset) {
            if (!is_readable(self::ASSETS . $asset)) {
                throw new \RuntimeException('Gate pass PDF artwork is unavailable.');
            }
        }
        if (($request->stage ?? '') !== 'issued' || !in_array($request->status ?? '', ['issued', 'rop_approved'], true)
            || ($pass->status ?? '') !== 'active' || !empty($pass->deleted)
            || (int) ($pass->gate_pass_request_id ?? 0) !== (int) ($request->id ?? 0)) {
            throw new \DomainException('Only an issued, active pass may use the approved transcript.');
        }
        $data = self::details($request, $pass, $visitors, $vehicles, $payment);
        $qr = Gate_pass_qr::png((string) ($pass->qr_token ?? ''), 6);
        $this->passNumber = (string) ($pass->gate_pass_no ?? '');
        $this->SetSubject($this->passNumber);
        $this->AddPage('P', 'A4');
        $this->stationery();
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.3);
        $this->Rect(4, 29, 200, 232);
        $this->heading('Online Gate Pass');

        foreach ([50, 84, 96, 108, 129, 141, 153, 219, 231] as $y) {
            $this->Line(4, $y, 204, $y);
        }
        $this->Line(63.5, 50, 63.5, 141);
        $this->Line(115, 50, 115, 141);
        $this->Line(159, 84, 159, 129);

        $this->label('Name:', 8, 58, 51.5);
        $this->textBox($data['name'], 8, 62, 51.5, 9, true, 'Name');
        $this->label('Card ID:', 8, 72, 51.5);
        $this->textBox($data['identity'], 8, 76, 51.5, 6, true, 'Card ID');
        $this->label('From:', 67.5, 58, 43.5);
        $this->textBox($data['from'], 67.5, 62, 43.5, 7, true);
        $this->label('To:', 67.5, 72, 43.5);
        $this->textBox($data['to'], 67.5, 76, 43.5, 7, true);
        $this->Image('@' . $qr, 146.5, 53, 26, 26, 'PNG');
        $this->textBox($this->passNumber, 119, 79.5, 81, 4, false, 'Gate pass number', 'C', 6.5);

        $this->pair('Email:', $data['email'], 'Mobile Number:', $data['phone'], 84, 12);
        $this->pair('Vehicle Number:', $data['plates'], 'Company/Organization:', $data['company'], 96, 12);
        $this->pair('Person Accompanying Visitors:', $data['escort'], 'Purpose of Visit:', $data['purpose'], 108, 21);
        $this->label('Request Date:', 8, 133, 51.5);
        $this->textBox($data['requested'], 67.5, 132, 43.5, 7, true);
        $this->textBox('Companions', 8, 145, 192, 5, true, '', 'C');

        $columns = [8, 54, 107.5, 142, 183, 200];
        $titles = ['Name', 'ID Card/Passport Number', 'Vehicle No.', 'Visitor Company', 'Remarks'];
        foreach ($titles as $i => $title) {
            $this->textBox($title, $columns[$i], 156, $columns[$i + 1] - $columns[$i], 4, true, '', 'L', 8);
        }
        for ($row = 0; $row < 4; $row++) {
            $visitor = $data['companions'][$row] ?? null;
            $values = $visitor ? [$visitor->full_name ?? '', $visitor->id_number ?? '', '-', $visitor->visitor_company ?? '', '-'] : ['', '', '', '', ''];
            foreach ($values as $i => $value) {
                $x = $columns[$i];
                $w = $columns[$i + 1] - $x;
                $y = 160 + $row * 12;
                $this->Rect($x, $y, $w, 12);
                if ($visitor) {
                    $this->textBox((string) $value, $x + 3, $y + 2, $w - 6, 8, false,
                        'Companion ' . ($row + 1) . ' - ' . $titles[$i]);
                }
            }
        }
        foreach (array_slice($data['companions'], 4) as $i => $visitor) {
            $this->overflow[] = ['Companion ' . ($i + 5),
                'Name: ' . ($visitor->full_name ?? '-') . "\nID Card/Passport Number: " . ($visitor->id_number ?? '-')
                . "\nVisitor Company: " . ($visitor->visitor_company ?? '-')];
        }
        $note = $data['individual'] ? 'This pass is valid only for the named visitor; companions require their own issued passes.' : '';
        if ($this->overflow) {
            $note .= ($note ? "\n" : '') . 'Additional details continue on page 3. HSSE instructions are on page 2.';
        }
        if ($note !== '') {
            $this->textBox($note, 8, 210, 192, 8, false, '', 'L', 7);
        }

        $this->textBox('Payment Details', 8, 223, 192, 5, false, '', 'C');
        $payColumns = [8, 46, 86, 132, 200];
        foreach (['You paid', 'Payment Id', 'Reference No', 'Transaction Number'] as $i => $title) {
            $x = $payColumns[$i];
            $w = $payColumns[$i + 1] - $x;
            $this->Rect($x, 235, $w, 5);
            $this->textBox($title, $x + 0.5, 235.7, $w - 1, 4, true);
            $this->Rect($x, 240, $w, 12);
            $this->textBox($data['payment'][$i], $x + 3, 242, $w - 6, 8, false, $title);
        }
        $this->textBox('Request: ' . (string) ($request->reference ?? ''), 8, 254, 192, 5, false, 'Request reference', 'C', 7);
        $this->SetTextColor(255, 0, 0);
        $this->textBox('APPROVED BY ROYAL OMAN POLICE', 8, 277, 194, 6, false, '', 'C', 15);
        $this->textBox('تمت الموافقة عليها من قبل شرطة عمان السلطانية', 8, 284, 194, 7, false, '', 'C', 14);
        $this->SetTextColor(0, 0, 0);

        // Supplied bilingual sheet, rendered once at 300 dpi. No runtime PDF
        // converter, remote images, GD, or Imagick are required on the server.
        $this->AddPage('P', 'A4');
        $this->Image(self::ASSETS . 'hsse-instructions.png', 0, 0, 210, 210 * 612 / 792, 'PNG');

        if ($this->overflow) {
            $this->continuation = true;
            $this->setPrintHeader(true);
            $this->SetAutoPageBreak(true, 35);
            $this->AddPage('P', 'A4');
            foreach ($this->overflow as [$label, $value]) {
                $this->SetFont('dejavusans', 'B', 9);
                $this->MultiCell(194, 5, $label, 0, 'L', false, 1);
                $this->SetFont('dejavusans', '', 9);
                $this->MultiCell(194, 5, $value, 0, 'L', false, 1);
                $this->Ln(3);
            }
        }
        return $this;
    }

    public function Header()
    {
        if ($this->continuation) {
            $this->stationery();
            $this->heading('Gate Pass - Additional Details');
            $this->SetFont('helvetica', '', 7);
            $this->SetXY(8, 46);
            $this->Cell(194, 4, $this->passNumber, 0, 0, 'C');
        }
    }

    private function stationery(): void
    {
        // This unmodified image is extracted from the user's approved transcript.
        // It already contains the supplied logo, gold logistics lines and footer.
        $autoBreak = $this->getAutoPageBreak();
        $margin = $this->getBreakMargin();
        $this->SetAutoPageBreak(false, 0);
        $this->Image(self::ASSETS . 'letterhead.jpg', 0, 0, 210, 276, 'JPEG');
        $this->SetAutoPageBreak($autoBreak, $margin);
    }

    private function heading(string $title): void
    {
        $this->SetFillColor(13, 111, 182);
        $this->Rect(8, 33, 192, 13.4, 'F');
        $this->SetTextColor(255, 255, 255);
        $this->textBox($title, 8, 36.3, 192, 8, true, '', 'C', 15);
        $this->SetTextColor(0, 0, 0);
    }

    private function label(string $label, float $x, float $y, float $width): void
    {
        $this->textBox($label, $x, $y, $width, 5, false);
    }

    private function pair(string $leftLabel, string $left, string $rightLabel, string $right, float $y, float $height): void
    {
        $this->label($leftLabel, 8, $y + ($height - 4) / 2, 51.5);
        $this->textBox($left, 67.5, $y + 2, 43.5, $height - 4, true, rtrim($leftLabel, ':'));
        $this->label($rightLabel, 119, $y + ($height - 4) / 2, 36);
        $this->textBox($right, 163, $y + 2, 37, $height - 4, true, rtrim($rightLabel, ':'));
    }

    /** Bound normal values; preserve long values in the appendix instead of clipping or hiding them. */
    private function textBox(string $value, float $x, float $y, float $w, float $h, bool $bold = false,
        string $overflowLabel = '', string $align = 'L', float $size = 8.25): void
    {
        $value = trim($value) ?: '-';
        $font = preg_match('/[^\x00-\x7F]/u', $value) ? 'dejavusans' : 'helvetica';
        do {
            $this->SetFont($font, $bold ? 'B' : '', $size);
            $height = $this->getStringHeight($w, $value, false, false, '', 0);
            if ($height <= $h || $size <= 6.5) { break; }
            $size -= 0.25;
        } while (true);
        if ($height > $h && $overflowLabel !== '') {
            $this->overflow[] = [$overflowLabel, $value];
            $value = 'See page 3';
            $this->SetFont('helvetica', $bold ? 'B' : '', 8);
        }
        $this->MultiCell($w, $h, $value, 0, $align, false, 0, $x, $y, true, 0, false, true, $h, 'M', true);
    }
}
