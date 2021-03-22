<?php

namespace GeoKrety\Controller\Cli;

use Base;
use Exception;
use GeoKrety\Model\WaypointOC;
use GeoKrety\Service\ConsoleWriter;
use GeoKrety\Service\File;
use League\Csv\Reader;
use PDOException;

class WaypointsImporterGeodashing extends WaypointsImporterBase {
    const GD_API_ENDPOINT = 'http://geodashing.gpsgames.org/Games/dashpoints_csv.zip';
    const GD_CACHE_DETAIL_URL = 'http://geodashing.gpsgames.org/cgi-bin/dp.pl?dp=%s';

    const SCRIPT_CODE = 'GEODASHING';
    const SCRIPT_NAME = 'waypoint_importer_geodashing';

    public function process() {
        $this->start();
        try {
            $this->perform_incremental_update();
        } catch (Exception $exception) {
            echo sprintf("\e[0;31mE: %s\e[0m", $exception->getMessage()).PHP_EOL;
        }
        $this->end();
    }

    /**
     * @throws Exception
     */
    private function perform_incremental_update() {
        echo sprintf("*** \e[0;33mRunning full import\e[0m").PHP_EOL;
        ob_flush();

        $tmp_file = tmpfile();
        $path = stream_get_meta_data($tmp_file)['uri'];
        $tmpdir = File::tmpdir();
        $csv_file = $tmpdir.'/dashpoints__all.csv';
        $nError = 0;

        File::download(self::GD_API_ENDPOINT, $path);
        File::extract_zip($path, $tmpdir);

        $db = Base::instance()->get('DB');
        $db->begin();

        $caches_count = count(file($csv_file));

        if ($caches_count) {
            $console_writer = new ConsoleWriter('Importing dashpoint %7s: %6.2f%% (%s/%d) - %d errors');
            $csv = Reader::createFromPath($csv_file);
            $records = $csv->getRecords();
            foreach ($records as $line => $values) {
                $name = $values[2];
                $wpt = new WaypointOC();
                $wpt->load(['waypoint = ?', $name]);
                $wpt->waypoint = $name;
                $wpt->provider = self::SCRIPT_CODE;
                $wpt->lat = number_format(floatval($values[0]), 5, '.', '');
                $wpt->lon = number_format(floatval($values[1]), 5, '.', '');
                $wpt->link = sprintf(self::GD_CACHE_DETAIL_URL, $name);
                $wpt->type = 'Dashpoint';
                try {
                    $wpt->save();
                } catch (PDOException $exception) {
                    ++$nError;
                    continue;
                }
                $console_writer->print([$name, $line / $caches_count * 100, $line, $caches_count, $nError]);
            }
            echo PHP_EOL;
        }
        $this->save_last_update();
        $db->commit();
    }
}
