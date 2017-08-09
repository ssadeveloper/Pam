<?php

namespace Pam;

class Vendors
{
    public static function getVendors($sort='name', $dir='ASC', $search='')
    {
        global $db;

        $search_q = '';
        if (!empty($search)) $search_q = "WHERE name LIKE '%$search%'";

        $q = mysqli_query($db, "SELECT vendor_id, name FROM vendors $search_q ORDER BY `$sort` $dir");
        if (!$q) {
            die(mysqli_error($db));
        }

        $array = [];
        while ($row = mysqli_fetch_assoc($q)) {
            $array[$row['vendor_id']] = $row;
        };

        return $array;
    }

    public static function getVendor($id)
    {
        global $db;

        $id = mysqli_real_escape_string($db, $id);
        $q = mysqli_query($db, "SELECT vendor_id, name FROM vendors WHERE vendor_id = '$id'");
        if (!$q) {
            die(mysqli_error($db));
        }

        $array = [];
        while ($row = mysqli_fetch_assoc($q)) {
            $array = $row;
        };

        return $array;
    }
}