<?php

namespace Pam\Site;

use Pam\Model;

class Level extends Model
{
    protected $tableName = 'site_levels';

    protected $idColumn = 'l_id';

    /**
     * @var array
     */
    private static $allItemsByCodes = [];
    /**
     * Get level code display.
     * All levels are cached by codes.
     *
     * @param string $bCode
     * @param string $lCode
     * @return bool|null
     */
    public function getCodeDisplayBulk($bCode, $lCode)
    {
        if (empty(self::$allItemsByCodes)) {
            $levels = $this->getAllWithBuildings();
            foreach ($levels as $level) {
                if (!array_key_exists($level['b_code'], self::$allItemsByCodes)) {
                    self::$allItemsByCodes[$level['b_code']] = [];
                }
                self::$allItemsByCodes[$level['b_code']][$level['l_code']] = $level;
            }
        }

        if (array_key_exists($bCode, self::$allItemsByCodes)
            && array_key_exists($lCode, self::$allItemsByCodes[$bCode])
        ) {
            return self::$allItemsByCodes[$bCode][$lCode]['l_code_display'];
        } else {
            return "??";
        }
    }

    /**
     * Returns all levels with Building data
     * 
     * @return array
     */
    public function getAllWithBuildings() {
        $q = "SELECT b.*, l.*, s.site_name, s.site_code FROM site_levels l
LEFT JOIN site_buildings b USING(b_id)
LEFT JOIN sites s ON b.site_id = s.site_id
";
        $result = $this->db->query($q);

        $array = [];
        while ($row = $result->fetch_assoc()) {
            $array[$row['l_id']] = $row;
        }

        return $array;
    }
}