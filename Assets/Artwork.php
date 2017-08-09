<?php
namespace Pam\Assets;

use Pam\Aws\S3;
use Pam\Db\Utils;

class Artwork
{
    /**
     * @var \ZipArchive
     */
    private $zip;

    private $assets;

    private $empty = true;

    private $icons;

    private $templateStatuses = [];

    private $allowUnapprovedTemplates;

    public function __construct($selectedLevels, $assetTypeIds, $status, $templateId)
    {
        global $db, $USER_TYPE;

        $select = 'SELECT a.asset_id, a.building, a.level, a.room_no, a.template_id, a.status_code, a.slat_data,
        at.asset_code, at.asset_code_pre, at.asset_code_suf,
        sb.site_id, sb.b_name, sb.address_data, 
        s.site_name, 
        sl.b_id, sl.l_code_display';
        $from = 'FROM assets a';
        $join = 'JOIN site_buildings sb ON a.building = sb.b_code
        JOIN site_levels sl ON a.level = sl.l_code AND sl.b_id = sb.b_id 
        JOIN sites s ON sb.site_id = s.site_id
        JOIN asset_types at ON a.asset_type_id = at.asset_type_id';
        $where = 'WHERE ';
        if ($templateId) {
            $templateId = mysqli_real_escape_string($db, $templateId);
            $where .= " a.template_id = '$templateId'";
            $this->allowUnapprovedTemplates = true;
        } elseif (isset($selectedLevels['locationId'], $selectedLevels['planId'])) {
            $locationId = mysqli_escape_string($db, $selectedLevels['locationId']);
            $planId = mysqli_escape_string($db, $selectedLevels['planId']);
            $where .= " a.building = '$locationId' AND a.level = '$planId' ";
        } else {
            $sites = getBuildingsAndLevelsGroupedBySite($selectedLevels);
            $levelConditions = [];
            foreach ($sites as $site) {
                foreach ($site as $building) {
                    $buildingCode = mysqli_real_escape_string($db, $building['b_code']);
                    $buildingLevels = Utils::arrayToInStatement(array_column($building['levels'], 'l_code'), $db);
                    $levelConditions[] = "(a.building = '$buildingCode' AND a.level IN ($buildingLevels))";
                }
            }
            $where .= ' (' . implode(' OR ', $levelConditions) . ') ';
        }
        if ($assetTypeIds) {
            $assetTypes = Utils::arrayToInStatement($assetTypeIds, $db);
            $where .= " AND a.asset_type_id IN ($assetTypes) ";
        }
        $where .= " AND status_code LIKE '02.%' ";
        $q = implode("\n", [$select, $from, $join, $where]);
        $res = mysqli_query($db, $q);
        if (!$res) {
            die(mysqli_error($db));
        }

        $this->assets = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $row['status_outcome'] = get_asset_outcome_NEW($row['status_code'], $USER_TYPE, $row['slat_data']);
            if ($status == "all" || $row['status_outcome'] == $status) {
                $this->assets[] = $row;
            }
        }
    }

    public function generateArtworkZip()
    {
        $fileName = tempnam('/tmp', 'zip');
        $this->zip = new \ZipArchive();
        $error = $this->zip->open($fileName, \ZipArchive::OVERWRITE);
        if ($error !== true) {
            throw new \Exception('Unable to create ZIP archive: ' . $error);
        }

        $this->archiveAssetTypes();

        if ($this->empty) {
            $this->zip->addFromString('empty.txt', 'There is no assets which match your selection.');
        }
        $this->zip->close();
        return $fileName;
    }

    protected function archiveAssetTypes()
    {
        foreach ($this->getAssetsByType() as $assetTypeCode => $assetsByTemplates) {
            foreach ($assetsByTemplates as $templateId => $assets) {
                $template = get_asset_type_template($templateId);
                $this->archiveAssetTypeTemplate($template, $assets);
            }
        }
    }

    protected function archiveAssetTypeTemplate($template, $assets)
    {
        $templateDir = $this->getTemplateFolderName($template);
        $this->archiveTemplateArtworkIndd($template, $templateDir);
        $this->archiveTemplateArtworkCsv($template, $templateDir, $assets);
    }

    protected function getTemplateFolderName($template)
    {
        return strtolower(str_replace(' ', '-', $template['asset_code'] . '-' . $template['template_number']));
    }

    protected function archiveTemplateArtworkIndd($template, $templateDir)
    {
        if (!$template['artwork_indesign_file']) {
            return;
        }
        $data = S3::instance()->getFile($template['artwork_indesign_file']);
        if (empty($data)) {
            return;
        }
        $this->zip->addFromString("/$templateDir/design.indd", $data);
        $this->empty = false;
    }

    protected function archiveTemplateArtworkCsv($template, $templateDir, $assets)
    {
        $mapping = TypeTemplates::getArtworkMapping($template['template_id']);
        if (!$mapping) {
            return;
        }
        $header = implode(',', TypeTemplates::getArtworkCsvColumns($template['artwork_csv_file'])) . "\n";
        $data = [];

        foreach ($assets as $asset) {
            $row = [];
            $slat_data = Assets::fillSlatData(json_decode($asset['slat_data'], true));
            if (array_key_exists($asset['asset_code'], Types::WITH_BUILDING) &&
                Types::WITH_BUILDING[$asset['asset_code']] == $template['template_number']) {
                $buildings = $this->getBuildings($slat_data);
            }
            $levels = array_keys(get_levels_by_bid($asset['b_id']));
            foreach ($mapping as $name => $value) {
                $parsedValue = static::parseValue($value);
                switch ($parsedValue['type']) {
                    case 'buildingCode':
                        $row[] = Assets::getBuildingNumberDisplay($asset['building']);
                        break;
                    case 'buildingName':
                        $row[] = $asset['b_name'];
                        break;
                    case 'buildingAddress':
                        $addressData = json_decode($asset['address_data'], true);
                        $row[] = isset($addressData['address']) ? $addressData['address'] : '';
                        break;
                    case 'levelCode':
                        $row[] = $asset['level'];
                        break;
                    case 'levelCodeDisplay' :
                        $row[] = $asset['l_code_display'];
                        break;
                    case 'roomId':
                        $row[] = $asset['room_no'];
                        break;
                    case 'siteName':
                        $row[] = $asset['site_name'];
                        break;
                    case 'campusName':
                        $campus = get_campus_data($asset['site_id']);
                        $row[] = isset($campus['building']['b_name']) ? $campus['building']['b_name'] : '';
                        break;
                    case 'message':
                        $row[] = $this->extractData($slat_data, $parsedValue, $asset);
                        break;
                    case 'icon':
                    case 'iconRight':
                        $row[] = $this->archiveIcons($parsedValue, $slat_data, $asset, $templateDir, 'pictograms');
                        break;
                    case 'arrow':
                        $row[] = $this->archiveIcons($parsedValue, $slat_data, $asset, $templateDir, 'arrows');
                        break;
                    case 'level':
                        $row[] = isset($levels[$parsedValue['item']]) ? $levels[$parsedValue['item']] : '';
                        break;
                    case 'room':
                        $row[] = $this->extractData($slat_data, $parsedValue, $asset);
                        break;
                    case 'building':
                        $row[] = isset($buildings[$value]) ? $buildings[$value] : '';
                        break;
                }
            }
            $data[] = '"' . implode('","', $row) . '"';
        }

        $this->zip->addFromString("/$templateDir/data.csv", $header . implode("\n", $data));
        $this->empty = false;
    }

    protected function archiveIcons($parsedValue, $slat_data, $asset, $directory, $subdirectory)
    {
        $index = $this->extractData($slat_data, $parsedValue, $asset);
        $icons = $this->getIcons();
        if (!isset($icons['icon_' . $index])) {
            return '';
        }
        $icon = $icons['icon_' . $index];
        $aiIconFileName = $icon['icon_name_id'] . '.ai';
        $svgIconFileName = $icon['icon_name_id'] . '.svg';
        if (S3::instance()->doesExist($aiIconFileName, Icon::S3_FOLDER)) {
            $iconFileName = $aiIconFileName;
        } else {
            $iconFileName = $svgIconFileName;
        }

        $iconData = S3::instance()->getFile($iconFileName, Icon::S3_FOLDER);
        $iconPath = "/$directory/$subdirectory/$iconFileName";
        $this->zip->addFromString($iconPath, $iconData);
        return "/$subdirectory/$iconFileName";
    }

    protected function getAssetsByType()
    {
        $assets = [];

        foreach ($this->assets as $asset) {
            $code = $asset['asset_code'];
            $templateId = $asset['template_id'];
            if (!$this->allowUnapprovedTemplates && !$this->isTemplateApproved($templateId)) {
                continue;
            }
            if (!isset($assets[$code])) {
                $assets[$code] = [];
            }
            if (!isset($assets[$code][$templateId])) {
                $assets[$code][$templateId] = [];
            }
            $assets[$code][$templateId][] = $asset;
        }
        return $assets;
    }

    protected function parseValue($value)
    {
        $reSide = '(side(?<side>\d+))?';
        $reGroup = '(group(?<group>\d+))?';
        $reType = '(?<type>message|icon|iconRight|arrow|level|room|building)';
        $reItem = '(?<item>\d+)';
        $reIndex = '(-(?<index>\d+))?';
        $re = "/^{$reSide}{$reGroup}{$reType}({$reItem}{$reIndex})?$/";
        if (preg_match($re, $value, $matches)) {
            return [
                'type' => $matches['type'],
                'side' => $matches['side'] ?: 1,
                'group' => !empty($matches['group']) ? ($matches['group'] - 1) : '',
                'item' => !empty($matches['item']) ? ($matches['item'] - 1) : '',
                'index' => !empty($matches['index']) ? ($matches['index'] - 1) : '',
            ];
        }
        return ['type' => $value];
    }

    protected function getIcons()
    {
        if (!$this->icons) {
            $this->icons = get_icons('all');
        }

        return $this->icons;
    }

    protected function extractData($slat_data, $parsedValue, $asset)
    {
        if (!isset($slat_data['side_' . $parsedValue['side']])) {
            return '';
        }
        $side_data = $slat_data['side_' . $parsedValue['side']];
        if (\isAssetWithSlatGroups($asset['asset_code_pre'], $asset['asset_code_suf'])) {
            $sideDataGrouped = Assets::regroupSlats($side_data);
            if (!isset($sideDataGrouped[$parsedValue['group']])) {
                return '';
            }
            $groupData = $sideDataGrouped[$parsedValue['group']];
            if ($parsedValue['type'] == 'arrow') {
                if (isset($groupData['dir'])) {
                    return $groupData['dir'];
                } else {
                    // default direction left icon code
                    return '32';
                }
            }
            if ($parsedValue['type'] == 'room' && empty($parsedValue['item'])) {
                return isset($groupData['room']) ? $groupData['room'] : '';
            }
            $side_data = $groupData['child_slats'];
        }
        $side_data = array_values($side_data);

        if (!isset($side_data[$parsedValue['item']])){
            return '';
        }
        $item = $side_data[$parsedValue['item']];
        switch ($parsedValue['type']) {
            case 'message':
                return isset($item['copy']) ? $item['copy'] : '';
            case 'icon':
                if (isset($item['icons'][$parsedValue['index']])) {
                    return $item['icons'][$parsedValue['index']];
                }
                return '';
            case 'iconRight':
                if (isset($item['icons_right'][$parsedValue['index']])) {
                    return $item['icons_right'][$parsedValue['index']];
                }
                return '';
            case 'room':
                return isset($item['room']) ? $item['room'] : '';

        }

        return '';
    }

    protected function getBuildings(&$slat_data)
    {
        $buildings = [];
        foreach ($slat_data as $side => &$side_data) {
            $buildingSlat = Assets::getBuildingSlatAndUnsetIt($side_data);
            if (is_array($buildingSlat) && isset($buildingSlat['building']) && strlen($buildingSlat['building'])) {
                $building = $buildingSlat['building'];
            } else {
                $building = 'xxx';
            }
            $index = substr($side, strlen('side_'));
            $buildings["side{$index}building"] = $building;
        }
        return $buildings;
    }

    protected function isTemplateApproved($templateId)
    {
        if (!isset($this->templateStatuses[$templateId])) {
            $this->templateStatuses[$templateId] = TypeTemplates::getArtworkStatus($templateId);
        }
        return $this->templateStatuses[$templateId]['comment_type'] == TypeTemplates::ARTWORK_APPROVED;
    }
}