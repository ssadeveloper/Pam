<?php
namespace Pam\Assets;

use Pam\Aws\S3;
use Pam\Db\Utils;
use Pam\Utils\Date;

class TypeTemplates
{
    const ARTWORK_VALUES = [
        'ID 1f' => [
            'A' => [
                'siteName' => 'Site Name',
                'campusName' => 'Campus Name',
            ],
        ],
        'ID 2f' => [
            'A' => [
                'siteName' => 'Site Name',
                'campusName' => 'Campus Name',
            ],
        ],
        'ID 5f' => [
            'A' => [
                'buildingCode' => 'Building Code',
                'buildingName' => 'Building Name',
                'buildingAddress' => 'Building Address',
            ],
        ],
        'ID 6w' => [
            'A' => [
                'buildingCode' => 'Building Code',
                'buildingName' => 'Building Name',
                'buildingAddress' => 'Building Address',
            ],
        ],
        'ID 7c' => [
            'A' => [
                'buildingCode' => 'Building Code',
                'buildingName' => 'Building Name',
            ],
        ],
        'ID 8w' => [
            'A' => [
                'levelCodeDisplay' => 'Level Code Display'
            ],
        ],
        'ID 9w' => [
            'A' => [
                'buildingCode' => 'Building Code',
                'levelCode' => 'Level Code',
                'roomId' => 'Room ID',
            ],
        ],
        'ID 10w' => [
            'A' => [
                'buildingCode' => 'Building Code',
                'levelCode' => 'Level Code',
                'roomId' => 'Room ID',
            ],
        ],
        'ID 11w' => [
            'A' => [
                'buildingCode' => 'Building Code',
                'levelCode' => 'Level Code',
                'roomId' => 'Room ID',
            ],
        ],
        'ID 12w' => [
            'A' => [
                'buildingCode' => 'Building Code',
                'levelCode' => 'Level Code',
                'roomId' => 'Room ID',
            ],
        ],
        'ID 13c' => [
            'A' => [
                'buildingCode' => 'Building Code',
                'levelCode' => 'Level Code',
                'roomId' => 'Room ID',
            ],
        ],
        'ID 14c' => [
            'A' => [
                'icon1-1' => 'Icon 1',
                'icon1-2' => 'Icon 2',
            ],
        ],
        'ID 15w' => [
            'A' => [
                'buildingCode' => 'Building Code',
                'levelCode' => 'Level Code',
                'roomId' => 'Room ID',
            ],
            'B' => [
                'buildingCode' => 'Building Code',
                'levelCode' => 'Level Code',
                'roomId' => 'Room ID',
            ],
        ],
        'ID 16w' => [
            'A' => [],
        ],
        'D 1f' => [
            'A' => [],
        ],
        'D 2f' => [
            'A' => [],
        ],
        'D 3f' => [
            'A' => [],
        ],
        'D 3w' => [
            'A' => [],
        ],
        'D 4w' => [
            'A' => [],
            'B' => [],
        ],
        'D 5s' => [
            'A' => [],
            'B' => [],
        ],
        'D 5w' => [
            'A' => [],
            'B' => [],
        ],
    ];

    const ARTWORK_APPROVED = 'artwork_approved';
    const ARTWORK_UNAPPROVED = 'artwork_unapproved';
    const ARTWORK_INDESIGN = 'indesign';
    const ARTWORK_CSV = 'csv';

    public static function getArtworkCsvColumns($csvFileName)
    {
        $data = S3::instance()->getFile($csvFileName);
        if (empty($data)) {
            return [];
        }

        $data = str_replace("\r\n", "\n", $data);
        $data = str_replace("\r", "\n", $data);
        $handle = fopen('data://text/plain,' . $data, 'r');
        $header = fgetcsv($handle);
        fclose($handle);
        return $header;
    }

    public static function setArtworkMapping($templateId, $artworkMapping)
    {
        global $db;
        foreach ($artworkMapping as $column => $value) {
            $values = Utils::arrayToInStatement([$templateId, $column, $value], $db);
            $value = mysqli_real_escape_string($db, $value);
            $q = "INSERT INTO asset_type_template_artworks (`template_id`, `column`, `value`) VALUES ($values)  
ON DUPLICATE KEY UPDATE `value` = '$value'";
            mysqli_query($db, $q) or die(mysqli_error($db));
        }
    }

    public static function getArtworkMapping($templateId)
    {
        global $db;
        $templateId = mysqli_real_escape_string($db, $templateId);
        $q = "SELECT * FROM asset_type_template_artworks WHERE template_id = '$templateId' ORDER BY `column`";
        $result = mysqli_query($db, $q);
        if (!$result) {
            die(mysqli_error($db));
        }
        $mapping = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $mapping[$row['column']] = $row['value'];
        }

        return $mapping;
    }

    public static function setArtworkApproved($templateId, $approved)
    {
        global $db, $USER_ID;
        $params = [
            $templateId,
            $approved ? 'Artwork approved for download' : 'Artwork unapproved for download',
            $approved ? static::ARTWORK_APPROVED : static::ARTWORK_UNAPPROVED,
            date("Y-m-d H:i:s"),
            $USER_ID,
        ];
        $params = Utils::arrayToInStatement($params, $db);


        $q = "INSERT INTO `asset_type_template_change_log` (`template_id` ,`comment`,`comment_type`,`date`,`u_id`) VALUES ($params)" ;

        return mysqli_query($db, $q) or die(mysqli_error($db));
    }

    public static function getArtworkStatus($templateId)
    {
        $row = static::getLastLogRecord($templateId);
        if ($row) {
            return $row;
        }
        return ['comment_type' => static::ARTWORK_UNAPPROVED, 'comment' => ''];
    }

    public static function getLastLogRecord($templateId, $type=null)
    {
        global $db;
        $templateId = mysqli_real_escape_string($db, $templateId);
        if ($type) {
            $type = mysqli_real_escape_string($db, $type);
            $q = "SELECT * FROM asset_type_template_change_log WHERE template_id = '$templateId' AND 
comment_type = '$type' ORDER BY `date` DESC LIMIT 1";
        } else {
            $q = "SELECT * FROM asset_type_template_change_log WHERE template_id = '$templateId' ORDER BY `date` DESC LIMIT 1";
        }

        $res = mysqli_query($db, $q) ;
        if (!$res) {
            die(mysqli_error($db));
        }

        $row = mysqli_fetch_assoc($res);
        if (!$row) {
            return null;
        }
        static $users;
        if (is_null($users)) {
            $users = get_users();
        }
        $user_name = "N/A";
        if (!empty($users['user_' . $row['u_id']]['full_name'])) {
            $user_name = $users['user_' . $row['u_id']]['full_name'];
        }
        $row['comment'] = $row['comment'] . " by: " . $user_name . " on " .
            Date::getClientDateTime($row['date']);
        return $row;
    }

    public static function logUpload($templateId, $type)
    {
        global $db, $USER_ID;
        $params = [
            $templateId,
            'Upload and configured',
            'artwork_upload_' . $type,
            date("Y-m-d H:i:s"),
            $USER_ID,
        ];
        $params = Utils::arrayToInStatement($params, $db);

        $q = "INSERT INTO `asset_type_template_change_log` (`template_id` ,`comment`,`comment_type`,`date`,`u_id`) VALUES ($params)" ;

        return mysqli_query($db, $q) or die(mysqli_error($db));
    }

    public static function getArtworkValues($assetCode, $templateNumber)
    {
        static $artworkValues;
        if (!$artworkValues) {
            $artworkValues = static::initArtworkValues();
        }
        if (isset($artworkValues[$assetCode][$templateNumber])) {
            return $artworkValues[$assetCode][$templateNumber];
        }

        return null;
    }

    private static function initArtworkValues()
    {
        $artworkValues = static::ARTWORK_VALUES;
        foreach ($artworkValues as $assetCode => $templates) {
            $assetType = Types::getByCode($assetCode);
            foreach (array_keys($templates) as $templateNumber) {
                static::initArtworkValuesFor($assetCode, $templateNumber, $assetType, $artworkValues);
            }
        }

        return $artworkValues;
    }

    private static function initArtworkValuesFor($assetCode, $templateNumber, $assetType, &$artworkValues)
    {
        $sides = $assetType['sides'];
        $slats = $assetType['slats'];
        $assetCodePre = $assetType['asset_code_pre'];
        $assetCodeSuf = $assetType['asset_code_suf'];
        for ($i = 1; $i <= $sides; $i++) {
            if (\isAssetWithSlatGroups($assetCodePre, $assetCodeSuf)) {
                for ($j = 1; $j <= \getMaxNumberGroups($assetCodePre, $assetCodeSuf, $templateNumber); $j++) {
                    $slats = \getMaxSlatsPerGroups($assetCodePre, $assetCodeSuf, $templateNumber);
                    static::generateArtworkValues($assetCode, $templateNumber, $slats, $i, $artworkValues, $j);
                }
            } else {
                static::generateArtworkValues($assetCode, $templateNumber, $slats, $i, $artworkValues);
            }
        }
    }

    private static function generateArtworkValues($assetCode, $templateNumber, $slats, $side, &$artworkValues,
                                                  $group = '', $iconNumber = 3, $levelNumber = 20)
    {
        if ($group) {
            $groupIndex = 'group' . $group;
            $groupName = 'Group ' . $group . ' ';
        } else {
            $groupIndex = '';
            $groupName = '';
        }

        for ($i = 1; $i <= $slats; $i++) {
            $artworkValues[$assetCode][$templateNumber]["side{$side}{$groupIndex}message{$i}"] =
                "Side $side {$groupName}Item $i Message";
            for ($j = 1; $j <= $iconNumber; $j++) {
                $artworkValues[$assetCode][$templateNumber]["side{$side}{$groupIndex}icon{$i}-{$j}"] =
                    "Side $side {$groupName}Item $i Icon $j";
            }
            for ($j = 1; $j <= $iconNumber; $j++) {
                $artworkValues[$assetCode][$templateNumber]["side{$side}{$groupIndex}iconRight{$i}-{$j}"] =
                    "Side $side {$groupName}Item $i Right Icon $j";
            }
            if (in_array($assetCode, Types::WITH_ROOMS)) {
                $artworkValues[$assetCode][$templateNumber]["side{$side}{$groupIndex}room{$i}"] =
                    "Side $side {$groupName}Item $i Room";
            }
        }

        if ($group && in_array($assetCode, Types::WITH_ARROWS)) {
            $artworkValues[$assetCode][$templateNumber]["side{$side}{$groupIndex}arrow"] = "Side $side {$groupName}Arrow";
        }

        if ($group && in_array($assetCode, Types::WITH_GROUP_ROOMS)) {
            $artworkValues[$assetCode][$templateNumber]["side{$side}{$groupIndex}room"] = "Side $side {$groupName}Room";
        }

        if (in_array($assetCode, Types::WITH_LEVELS)) {
            for ($i = 1; $i <= $iconNumber; $i++) {
                $artworkValues[$assetCode][$templateNumber]["side{$side}{$groupIndex}level{$i}"] = "Level {$i}";
            }
        }

        if (array_key_exists($assetCode, Types::WITH_BUILDING) &&
            Types::WITH_BUILDING[$assetCode] == $templateNumber) {
            $artworkValues[$assetCode][$templateNumber]["side{$side}building"] = "Side $side Building";
        }
    }
}