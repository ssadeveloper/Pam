<?php
namespace Pam\Gdal;
use Pam\Geo\Point;
use Pam\Log;
use Symfony\Component\Process\ProcessBuilder;

/**
 * Class Coordinates
 * Converts point from one CRS (coordinate reference system) to another CRS using 'cs2cs' CLI
 */
class Coordinates
{
    const CS2CS_CLI = 'cs2cs';
    const EPSG_3857 = 'epsg:3857';
    const EPSG_4326 = 'epsg:4326';

    /**
     * @var Coordinates
     */
    private static $instance;

    /**
     * @return Coordinates
     */
    public static function get()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct()
    {
    }

    /**
     * Transform point coordinates from one CRS to another
     * @param Point[] $points
     * @param string $sourceCRS
     * @param string $destCRS
     * @return array
     */
    public function transform($points, $sourceCRS = 'epsg:3857', $destCRS = 'epsg:4326') {
        $args = [static::CS2CS_CLI, "+init={$sourceCRS}", "+to",  "+init={$destCRS}", '-f', '"%.16f"'];

        $input = '';
        foreach ($points as $point) {
            $input .= "{$point->x} {$point->y}\n";
        }

        $processBuilder = new ProcessBuilder($args);
        $process = $processBuilder->setInput($input)->getProcess();
        $exitCode = $process->run();
        $output = $process->getOutput();

        $this->checkProcessResult($exitCode, $output, $process->getErrorOutput());

        $outputArray = explode("\n", $output);
        $result = [];
        $longitude = null;
        $latitude = null;
        foreach ($points as $key => $point) {
            //parse the string in following format:
            //"151.1983219229697681"\t"-33.8835062953016291" "0.0000000000000000"
            $outputString = $outputArray[$key];
            $outputString = str_replace('"', '', $outputString);
            $exploded = explode("\t", $outputString);
            $longitude = $exploded[0];
            $latitude = explode(" ", $exploded[1])[0];
            $result[$key] = new Point(floatval($longitude), floatval($latitude));
        }
        return $result;
    }

    /**
     * @param int $exitCode
     * @param string $output
     * @param string $errorOutput
     * @throws Exception
     */
    private function checkProcessResult($exitCode, $output, $errorOutput)
    {
        if ($exitCode == 0) {
            return;
        }
        $cs2csCli = static::CS2CS_CLI;
        $message = "Unable to transform coordinates, {$cs2csCli} utility exited with code {$exitCode}, output: {$output}, " .
            " error output: {$errorOutput}";
        Log::get()->addError($message);
        throw new Exception($message);
    }
}