<?php
namespace Pam\Pdf;

require_once(dirname($_SERVER["DOCUMENT_ROOT"]) . "/html/fpdf/fpdf.php");
require_once(dirname($_SERVER["DOCUMENT_ROOT"]) . "/html/fpdf/fpdi.php");
require_once(dirname($_SERVER["DOCUMENT_ROOT"]) . "/html/fpdf/fpdf_extras.php");
require_once(dirname($_SERVER["DOCUMENT_ROOT"]) . "/html/fpdf/fpdf_PAM.php");

class LocationsSchedule extends \PDF_PAM
{
    private $isBuildingCoverPage = false;

    private $headerData = [];

    /**
     * @param boolean $isBuildingCoverPage
     */
    public function setIsBuildingCoverPage($isBuildingCoverPage)
    {
        $this->isBuildingCoverPage = $isBuildingCoverPage;
    }

    public function setHeaderData($headerData)
    {
        $this->headerData = $headerData;
    }

    public function Header()
    {
        if ($this->PageNo() == 1 || $this->isBuildingCoverPage) {
            $this->CoverPage();
        } else {
            $this->addRegularPageHeader();
        }
    }

    public function AcceptPageBreak()
    {
        $this->TableDividers($this->header_h, $this->headerData);
        $this->SetDrawColor('#cccccc');
        $this->SetLineWidth(0.25);
        $this->SetDash(.5, .5);
        $this->Line(0, $this->GetY(), $this->w, $this->GetY());
        return true;
    }
}