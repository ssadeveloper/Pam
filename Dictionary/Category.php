<?php

namespace Pam\Dictionary;

use Pam\Model;

class Category extends Model
{
    protected $tableName = 'dictionary_categories';

    protected $idColumn = 'cat_id';

    /**
     * @var array
     */
    private static $allItems = [];
    /**
     * Is primary dictionary category.
     * Optimized for processing many queries.
     *
     * @param int $catId
     * @return bool|null
     */
    public function isPrimaryDicCatBulk($catId)
    {
        if (empty(self::$allItems)) {
            self::$allItems = $this->getAll();
        }

        $cat = array_key_exists($catId, self::$allItems) ? self::$allItems[$catId] : null;
        
        if ($cat) {
            if($cat['cat_type'] == "Primary"){
                return true;
            }else{
                return false;
            }
        } else {
            return null;
        }
    }
}