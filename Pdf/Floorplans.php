<?php
namespace Pam\Pdf;

require_once(dirname($_SERVER["DOCUMENT_ROOT"]) . "/html/fpdf/fpdf.php");
require_once(dirname($_SERVER["DOCUMENT_ROOT"]) . "/html/fpdf/fpdi.php");
require_once(dirname($_SERVER["DOCUMENT_ROOT"]) . "/html/fpdf/fpdf_extras.php");
require_once(dirname($_SERVER["DOCUMENT_ROOT"]) . "/html/fpdf/fpdf_PAM.php");

class Floorplans extends \PDF_PAM
{
    private $isBuildingCoverPage = false;

    /**
     * @param boolean $isBuildingCoverPage
     */
    public function setIsBuildingCoverPage($isBuildingCoverPage)
    {
        $this->isBuildingCoverPage = $isBuildingCoverPage;
    }

    public function Header()
    {
        if ($this->PageNo() == 1 || $this->isBuildingCoverPage) {
            $this->CoverPage();
        } else {
            $this->addRegularPageHeader();
        }
    }

    public function createIcon($x, $y, $width, $sides, $clr, $scale, $rot, $lx, $ly, $label, $label_clr)
    {
        $icon_height = .2;
        $this->Rotate($rot, $x, $y);
        $this->SetFillColor("#" . $clr);
        $this->Rect($x - ($width * .5), $y - $icon_height, $width, $icon_height, 'F');
        if ($sides == 1) {
            $this->SetFillColor("#000000");
            $this->Rect($x - ($width * .5), $y, $width, $icon_height, 'F');
        } else {
            $this->SetFillColor("#" . $clr);
            $this->Rect($x - ($width * .5), $y, $width, $icon_height, 'F');
            $this->SetFillColor("#000000");
            $this->Rect($x - ($width * .5), $y - .01, $width, .02, 'F');
        }

        //ADD ARROW
        $arrow_width = 1.2;
        $this->setSourceFile('assets/a_arrow.pdf');
        $tplIdx = $this->importPage(1);
        $arrow_dimensions = $this->getTemplateSize($tplIdx, $arrow_width);
        $this->useTemplate($tplIdx, $x - $arrow_width * .5, $y - $arrow_dimensions['h'], $arrow_width);
        $this->Rotate(0);

        //ADD LABEL
        $this->SetFillColor("#" . $label_clr);
        $this->SetFont('DIN-Medium', '', 6);
        $this->SetTextColor('white');
        $this->SetXY($lx, $ly);
        $lw = $this->GetStringWidth($label) + 2;
        $lh = 2.8;
        $this->Cell($lw, $lh, $label, 0, 1, 'C', true);
        $label_corners = array(
            array($lx, $ly),
            array($lx + $lw, $ly),
            array($lx + $lw, $ly + $lh),
            array($lx, $ly + $lh)
        );

        //workout X and Y of both edges on rotated icon
        $radius = $width * .5;
        $edge_x = $x + $radius * cos(deg2rad(-$rot));
        $edge_y = $y + $radius * sin(deg2rad(-$rot));
        $edge2_x = $x + $radius * cos(deg2rad(180 - $rot));
        $edge2_y = $y + $radius * sin(deg2rad(180 - $rot));

        //Select draw corners....
        //target edge of icon
        $edge1 = false;
        $line_x1 = 0;
        $line_y1 = 0;
        $line_x2 = 0;
        $line_y2 = 0;
        $shortest_distance = 0;

        foreach ($label_corners as $crn) {
            $x1 = $crn[0];
            $y1 = $crn[1];
            $edge1_dis = static::hypotenuse(abs($edge_x - $x1), abs($edge_y - $y1));
            $edge2_dis = static::hypotenuse(abs($edge2_x - $x1), abs($edge2_y - $y1));
            if (empty($shortest_distance) || $shortest_distance > $edge1_dis) {
                $shortest_distance = $edge1_dis;
                $line_x1 = $x1;
                $line_y1 = $y1;
                $line_x2 = $edge_x;
                $line_y2 = $edge_y;
                $edge1 = true;
            }
            if (empty($shortest_distance) || $shortest_distance > $edge2_dis) {
                $shortest_distance = $edge2_dis;
                $line_x1 = $x1;
                $line_y1 = $y1;
                $line_x2 = $edge2_x;
                $line_y2 = $edge2_y;
                $edge1 = false;
            }
        }

        //DRAW LINE TO LABEL
        $this->SetDrawColor("#" . $label_clr);
        $this->SetLineWidth(.1);
        $this->Line($line_x1, $line_y1, $line_x2, $line_y2);
        $this->SetFillColor("#" . $label_clr);

        //ADD Connection dot
        if ($edge1) {
            $this->Circle($edge_x, $edge_y, .3, 'F');
        } else {
            $this->Circle($edge2_x, $edge2_y, .3, 'F');
        }
    }

    //TODO: move this function to parent class and remove duplicates
    private static function hypotenuse($a, $b)
    {
        return $c = sqrt($a * $a + $b * $b);
    }
}
