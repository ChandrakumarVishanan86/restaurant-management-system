<?php
declare(strict_types=1);
require_once APP_ROOT . '/lib/fpdf/fpdf.php';

/** Latin-1 safe text for FPDF core fonts. */
function pdf_text(?string $s): string
{
    $s = (string) $s;
    $out = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
    return $out === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) : $out;
}

/** Restaurant letterhead + footer shared by every PDF. */
class DfPdf extends FPDF
{
    public string $docTitle = '';
    public function Header(): void
    {
        $this->SetFillColor(234, 88, 12); $this->Rect(0, 0, 210, 30, 'F');
        $this->SetFillColor(194, 65, 12); $this->Rect(130, 0, 80, 30, 'F');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 18); $this->SetXY(14, 7); $this->Cell(0, 8, pdf_text(RESTAURANT_NAME));
        $this->SetFont('Helvetica', '', 8.5); $this->SetXY(14, 16); $this->Cell(0, 5, pdf_text(RESTAURANT_ADDRESS . '  |  ' . RESTAURANT_PHONE));
        $this->SetFont('Helvetica', 'B', 11); $this->SetXY(-84, 10); $this->Cell(70, 6, pdf_text(strtoupper($this->docTitle)), 0, 0, 'R');
        $this->SetXY(-84, 17); $this->SetFont('Helvetica', '', 8.5); $this->Cell(70, 5, 'Printed: ' . date('d M Y H:i'), 0, 0, 'R');
        $this->SetY(36); $this->SetTextColor(30, 41, 59);
    }
    public function Footer(): void
    {
        $this->SetY(-14); $this->SetDrawColor(220, 226, 232); $this->Line(14, $this->GetY(), 196, $this->GetY());
        $this->SetFont('Helvetica', 'I', 8); $this->SetTextColor(120, 130, 145);
        $this->Cell(0, 8, 'Computer generated document  -  ' . pdf_text(RESTAURANT_NAME) . '    Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    public function heading(string $t): void
    {
        $this->SetFont('Helvetica', 'B', 11); $this->SetTextColor(234, 88, 12); $this->Cell(0, 8, pdf_text($t), 0, 1);
        $this->SetTextColor(30, 41, 59);
    }
    public function tableHead(array $cols): void
    {
        $this->SetFillColor(234, 88, 12); $this->SetTextColor(255, 255, 255); $this->SetFont('Helvetica', 'B', 9); $this->SetDrawColor(234, 88, 12);
        foreach ($cols as [$l, $w, $a]) { $this->Cell($w, 8, pdf_text($l), 1, 0, $a, true); }
        $this->Ln(); $this->SetTextColor(30, 41, 59); $this->SetFont('Helvetica', '', 9); $this->SetDrawColor(226, 232, 240);
    }
}
