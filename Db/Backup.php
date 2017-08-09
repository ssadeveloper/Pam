<?php
namespace Pam\Db;


use Pam\Aws\S3;

class Backup
{
    const BACKUP_PATH = 'db-backup';

    /**
     * @var static
     */
    private static $instance;

    /**
     * @return static
     */
    public static function get()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct()
    {}

    /**
     * @param string $fileName
     * @param string[] $ignore
     * @return null|string
     */
    public function backup($fileName, $ignore)
    {
        global $db;

        //get all of the tables
        $tables = array();
        $result = mysqli_query($db, "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        while ($row = mysqli_fetch_row($result)) {
            $tables[] = $row[0];
        }

        //disable foreign keys (to avoid errors)
        $return = 'SET FOREIGN_KEY_CHECKS=0;' . "\n";
        $return .= 'SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";' . "\n";
        $return .= 'SET AUTOCOMMIT=0;' . "\n";
        $return .= 'START TRANSACTION;' . "\n";

        //cycle through
        foreach ($tables as $table) {
            if (!empty($ignore) && in_array($table, $ignore)) {
                continue;
            }

            $result = mysqli_query($db, 'SELECT * FROM ' . $table);
            $num_fields = mysqli_num_fields($result);
            $num_rows = mysqli_num_rows($result);
            $i_row = 0;

            $row2 = mysqli_fetch_row(mysqli_query($db, 'SHOW CREATE TABLE ' . $table));
            $return .= "\n\n" . $row2[1] . ";\n\n";
            $return .= "TRUNCATE TABLE `{$table}`;\n";

            if ($num_rows !== 0) {
                $row3 = mysqli_fetch_fields($result);
                $return .= 'INSERT INTO ' . $table . '( ';
                foreach ($row3 as $th) {
                    $return .= '`' . $th->name . '`, ';
                }
                $return = substr($return, 0, -2);
                $return .= ' ) VALUES';

                for ($i = 0; $i < $num_fields; $i++) {
                    while ($row = mysqli_fetch_row($result)) {
                        $return .= "\n(";
                        for ($j = 0; $j < $num_fields; $j++) {
                            $row[$j] = addslashes($row[$j]);
                            $row[$j] = preg_replace("#\n#", "\\n", $row[$j]);
                            if (isset($row[$j])) {
                                $return .= '"' . $row[$j] . '"';
                            } else {
                                $return .= '""';
                            }
                            if ($j < ($num_fields - 1)) {
                                $return .= ',';
                            }
                        }
                        if (++$i_row == $num_rows) {
                            $return .= ");"; // last row
                        } else {
                            $return .= "),"; // not last row
                        }
                    }
                }
            }
            $return .= "\n\n\n";
        }

        // enable foreign keys
        $return .= 'SET FOREIGN_KEY_CHECKS=1;' . "\n";
        $return .= 'COMMIT;';
        $return = str_replace("CREATE TABLE", "CREATE TABLE IF NOT EXISTS", $return);

        return S3::instance()->save($return, 'sql', $fileName, static::BACKUP_PATH);
    }

    /**
     * @param string $fileName
     * @return bool
     */
    public function restore($fileName)
    {
        global $hostname_db, $username_db, $password_db, $database_db;

        $backupFile = S3::instance()->getFile($fileName, static::BACKUP_PATH);

        $lines = explode("\n", $backupFile);
        $query = '';
        $multiComment = false;
        foreach ($lines as $line) {
            // Skip it if it's a comment
            if (substr($line, 0, 2) == '--' || $line == '')
                continue;
            if (substr($line, 0, 2) == '/*') {
                $multiComment = true;
            }

            // Add this line to the current segment
            if (!$multiComment) {
                $query .= $line;
            }

            if (substr(trim($line), -2, 2) == '*/') {
                $multiComment = false;
            }
        }

        //we should connect to DB using admin credentials due to lack of privileges of client DB user
        $adminDbConnection = mysqli_connect($hostname_db, $username_db, $password_db, $database_db) or die("Error: " . mysqli_error($adminDbConnection));
        return mysqli_multi_query($adminDbConnection, "$query");
    }

    /**
     * @param string $fileName
     * @return bool
     */
    public function delete($fileName)
    {
        return S3::instance()->delete($fileName, static::BACKUP_PATH);
    }

    /**
     * @param string $fileName
     * @return bool
     */
    public function isBackupDumpExist($fileName)
    {
        return S3::instance()->doesExist($fileName, static::BACKUP_PATH);
    }
}
