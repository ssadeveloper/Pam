<?php
namespace Pam\Assets;

use Pam\Db\Utils;
use Pam\Model;

class VariationCost extends Model
{

    protected $tableName = 'asset_variation_costs';

    protected $idColumn = 'vc_id';

    /**
     * @param int|string $assetId
     * @param string $reportStart
     * @param string $reportEnd
     * @param array $statuses
     * @return array
     */
    public function get($assetId, $reportStart = "", $reportEnd = "", $statuses = ['complete'])
    {
        list($rs_day, $rs_month, $rs_year) = explode("/", $reportStart);
        list($re_day, $re_month, $re_year) = explode("/", $reportEnd);
        $date_start = "$rs_year-$rs_month-$rs_day";
        $date_end = "$re_year-$re_month-$re_day";
        
        $statusQuery = '';
        if (!empty($statuses)) {
            $statusQuery = " AND status IN (" . Utils::arrayToInStatement($statuses, $this->db) . ") "; 
        }
        
        $result = mysqli_query($this->db,"SELECT * FROM `asset_variation_costs`
 WHERE asset_id = '$assetId' $statusQuery AND date_complete BETWEEN '$date_start 00:00:00' AND '$date_end 23:59:59'");
        $array = array();
        while($row =  mysqli_fetch_assoc($result)){
            $array[$row['l_id']] = $row;
        };
        
        return $array;
    }
}