<?php
namespace Pam\Assets;


use Pam\Model;

class Building extends Model
{
    protected $tableName = 'site_buildings';

    protected $idColumn = 'b_id';

    /**
     * @param string $code
     * @return array row with specified id
     */
    public function getOneByBuildingCode($code)
    {
        $code =  $this->db->real_escape_string($code);
        $query = "SELECT * FROM `{$this->tableName}` WHERE `b_code` = '$code' LIMIT 1";
        $result = $this->db->query($query);
        if (!$result) {
            die($this->db->error);
        }
        return $result->fetch_assoc();
    }
}