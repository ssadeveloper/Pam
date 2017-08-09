<?php
namespace Pam\Pdf;

use Pam\Aws\S3;

require_once(dirname($_SERVER["DOCUMENT_ROOT"]) . "/html/fpdf/fpdf.php");
require_once(dirname($_SERVER["DOCUMENT_ROOT"]) . "/html/fpdf/fpdi.php");
require_once(dirname($_SERVER["DOCUMENT_ROOT"]) . "/html/fpdf/fpdf_extras.php");
require_once(dirname($_SERVER["DOCUMENT_ROOT"]) . "/html/fpdf/fpdf_PAM.php");

class LocationDetail extends \PDF_PAM
{
    private $isBuildingCoverPage = false;

    /**
     * @param boolean $isBuildingCoverPage
     */
    public function setIsBuildingCoverPage($isBuildingCoverPage)
    {
        $this->isBuildingCoverPage = $isBuildingCoverPage;
    }
    public function AcceptPageBreak()
    {
        $this->SetDrawColor('#cccccc');
        $this->SetLineWidth(0.25);
        $this->SetDash(.5, .5);
        $this->Line(0, $this->GetY() + 2, $this->w, $this->GetY() + 2);
        return true;
    }

    public function Header()
    {
        if ($this->PageNo() == 1 || $this->isBuildingCoverPage) {
            $this->CoverPage();
        } else {
            $this->addRegularPageHeader();
        }
    }

    public function addImage(
        $img,
        $xVal,
        $yVal,
        $f_width = 83,
        $f_height = 56,
        $bg = true,
        $valign = "middle"
    ) {
        //add image square
        $this->SetFillColor(245, 245, 245);
        if ($bg) {
            $this->Rect($xVal, $yVal, $f_width, $f_height, 'F');
        }
        if (empty($img) || !S3::instance()->doesExist($img['name'])) {
            return;
        }

        $file_name = S3::instance()->getPresignedUrl($img['name']);

        list($width, $height, $type, $attr) = getimagesizefromstring(S3::instance()->getFile($img['name']));
        if (($width / $f_width) > ($height / $f_height)) {
            $scale = $f_width / $width;
        } else {
            $scale = $f_height / $height;
        }
        $img_xVal = $xVal;
        $img_yVal = $yVal;
        if (($width / $f_width) > ($height / $f_height)) {
            $img_yVal = $yVal + ($f_height - ($height * $scale)) * .5;
        } else {
            $img_xVal = $xVal + ($f_width - ($width * $scale)) * .5;
        }
        if ($valign == "top") {
            $img_yVal = $yVal;
        }
        $this->Image($file_name, $img_xVal, $img_yVal, $width * $scale, $height * $scale, $img['ext']);
    }

    public function addDetail($label, $copy, $header_w = 30)
    {
        $this->SetFont('NettoOT-Bold', '', $this->body_font_size);
        $this->Cell($header_w, $this->body_leading, strtoupper($label), 0, 0, 'L', false);
        $this->SetFont('NettoOT-Light', '', $this->body_font_size);
        $this->MultiCell(0, $this->body_leading, $copy, 0, 'L');
        $this->SetFont('NettoOT-Bold', '', $this->body_font_size);
        $this->SetY($this->GetY() + 3);
    }

    public function addNotesDetail($label, $copy)
    {
        $this->SetFont('NettoOT-Bold', '', $this->body_font_size);
        $this->MultiCell(0, $this->body_leading, $label, 0, 'L');
        $this->SetFont('NettoOT-Light', '', $this->body_font_size);
        $this->MultiCell(0, $this->body_leading, $copy, 0, 'L');
        $this->SetFont('NettoOT-Bold', '', $this->body_font_size);
        $this->SetY($this->GetY() + 3);
    }
}