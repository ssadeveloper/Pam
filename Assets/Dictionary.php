<?php

namespace Pam\Assets;

class Dictionary
{
    const ICON_POSITION_LEFT = 'left';
    const ICON_POSITION_RIGHT = 'right';
    const ICON_POSITION_BOTH = 'both';

    /**
     * @var Dictionary
     */
    private static $instance;

    /**
     * @var array
     */
    private static $allItems = [];

    /**
     * DB connection
     * @var resource
     */
    private $db;

    private function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * @return Dictionary
     */
    public static function get()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * @param int $dictionaryId
     * @param int[] $iconIds
     * @param string $iconPosition
     */
    public function assignIcons($dictionaryId, $iconIds, $iconPosition = self::ICON_POSITION_LEFT)
    {
        $this->clearIconsAssignment($dictionaryId, $iconPosition);
        if (empty($iconIds)) {
            return;
        }
        $dictionaryId = mysqli_escape_string($this->db, $dictionaryId);
        $isRightIcon = $iconPosition == static::ICON_POSITION_RIGHT ? 'true' : 'false';
        $values = [];
        foreach ($iconIds as $iconId) {
            $iconId = mysqli_escape_string($this->db, $iconId);
            $values[] = "({$dictionaryId}, {$iconId}, {$isRightIcon})";
        }
        $valuesStatement = implode(', ', $values);
        $query = "INSERT INTO `dictionary_icons` (`dictionaryId`, `iconId`, `isRightIcon`) VALUES {$valuesStatement}";
        mysqli_query($this->db, $query) or die(mysqli_errno($this->db));
    }

    /**
     * @param int $dictionaryId
     * @param string $iconPosition
     */
    public function clearIconsAssignment($dictionaryId, $iconPosition = self::ICON_POSITION_BOTH)
    {
        $dictionaryId = mysqli_escape_string($this->db, $dictionaryId);
        $iconPositionCondition = '';
        if ($iconPosition == static::ICON_POSITION_LEFT) {
            $iconPositionCondition = 'AND `isRightIcon` = false';
        } else if ($iconPosition == static::ICON_POSITION_RIGHT) {
            $iconPositionCondition = 'AND `isRightIcon` = true';
        }
        $query = "DELETE FROM `dictionary_icons` WHERE `dictionaryId` = {$dictionaryId} {$iconPositionCondition}";
        mysqli_query($this->db, $query) or die(mysqli_errno($this->db));
    }

    /**
     * @param int $dictionaryId
     * @return array
     */
    public function getAssignedIcons($dictionaryId) {
        $dictionaryId = mysqli_escape_string($this->db, $dictionaryId);
        $query = "SELECT iconId, isRightIcon FROM `dictionary_icons` WHERE `dictionaryId` = {$dictionaryId} ORDER BY id";
        $result = mysqli_query($this->db, $query) or die(mysqli_errno($this->db));
        $iconIds = [
            'left' => [],
            'right' => [],
        ];
        while($iconAssignment = mysqli_fetch_assoc($result)) {
            if ($iconAssignment['isRightIcon'] == 1) {
                $iconIds['right'][] = $iconAssignment['iconId'];
            } else {
                $iconIds['left'][] = $iconAssignment['iconId'];
            }
        }
        return $iconIds;
    }

    /**
     * Return dictionary item.
     * Optimized for processing many queries.
     * 
     * @param int $id
     * @return array|null
     */
    public function getItemOptimizedForBulk($id)
    {
        if (empty(self::$allItems)) {
            $q = "SELECT * FROM dictionary";
            $qr = mysqli_query($this->db, $q);
            while ($row = mysqli_fetch_assoc($qr)) {
                substr($row['status_code'], 3, 1) ? $row['copy_approved'] = 'Y' : $row['copy_approved'] = 'N';
                $row['d_code'] = str_pad($row['d_id'], 4, "0", STR_PAD_LEFT);
                self::$allItems[$row['d_id']] = $row;
            };
        }

        return array_key_exists($id, self::$allItems) ? self::$allItems[$id] : null;
    }
}