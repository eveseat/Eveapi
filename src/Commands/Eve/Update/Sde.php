<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to present Leon Jacobs
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Seat\Eveapi\Commands\Eve\Update;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Seat\Eveapi\Models\Sde\MapDenormalize;
use Seat\Services\Helpers\AnalyticsContainer;
use Seat\Services\Jobs\Analytics;
use Seat\Services\Settings\Seat;

/**
 * Class Sde.
 *
 * @package Seat\Eveapi\Commands\Eve\Update
 */
class Sde extends Command
{
    use DispatchesJobs;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eve:update:sde
                            {--local : Check the local config file for the version string}
                            {--force : Force re-installation of an existing SDE version}
                            {--json=https://raw.githubusercontent.com/eveseat/resources/master/sde.json : Specify the metadata to update the SDE}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the EVE Online SDE Data';

    /**
     * The Guzzle Instance.
     *
     * @var
     */
    protected $guzzle;

    /**
     * The response Json from the resources repo.
     *
     * @var
     */
    protected $json;

    /**
     * The SDE file storage path.
     *
     * @var
     */
    protected $storage_path;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {

        parent::__construct();

    }

    /**
     * Query the eveseat/resources repository for SDE
     * related information.
     *
     * @throws \Seat\Services\Exceptions\SettingException
     */
    public function handle()
    {

        // Start by warning the user about the command that will be run
        $this->comment('Warning! This Laravel command uses exec() to execute a ');
        $this->comment('shell command to import an extracted dump. Due');
        $this->comment('to the way the command is constructed, should someone ');
        $this->comment('view the current running processes of your server, they ');
        $this->comment('will be able to see your SeAT database users password.');
        $this->line('');
        $this->line('Ensure that you understand this before continuing.');

        // Test that we have valid Database details. An exception
        // will be thrown if this fails.
        DB::connection()->getDatabaseName();

        if (! $this->confirm('Are you sure you want to update to the latest EVE SDE?', true)) {

            $this->warn('Exiting');

            return $this::SUCCESS;
        }

        // Request the json from eveseat/resources
        $this->json = $this->getJsonResource();

        // Ensure we got a response, else fail.
        if (! $this->json) {

            $this->warn('Unable to reach the resources endpoint.');

            return $this::FAILURE;
        }

        // Check if we should attempt getting the
        // version string locally
        if ($this->option('local')) {

            $version_number = env('SDE_VERSION', null);

            if (! is_null($version_number)) {

                $this->comment('Using locally sourced version number of: ' . $version_number);
                $this->json->version = env('SDE_VERSION');

            } else {

                $this->warn('Unable to determine the version number override. ' .
                    'Using remote version: ' . $this->json->version);
            }
        }

        // add extra tables registered on behalf providers
        $extra_tables = config('seat.sde.tables', []);
        $this->json->tables = array_unique(array_merge($this->json->tables, $extra_tables));
        sort($this->json->tables, SORT_STRING);

        $all_sde_tables_exist = true;
        foreach ($this->json->tables as $table) {
            if(! Schema::hasTable($table)) {
                $all_sde_tables_exist = false;
            }
        }

        // Avoid an existing SDE to be accidentally installed again
        // except if the user explicitly ask for it
        if ($this->json->version == Seat::get('installed_sde') &&
            $this->option('force') == false &&
            $all_sde_tables_exist
        ) {

            $this->warn('You are already running the latest SDE version.');
            $this->warn('If you want to install it again, run this command with --force argument.');

            return $this::SUCCESS;
        }

        // Ask for a confirmation before installing an existing SDE version
        if ($this->option('force') == true) {

            $this->warn('You will re-download and install the current SDE version.');

            if (! $this->confirm('Are you sure ?', true)) {

                $this->info('Nothing has been updated.');

                return $this::SUCCESS;
            }
        }

        // Show a final confirmation with some info on what
        // we are going to be doing.
        $this->info('The local SDE data will be updated to ' . $this->json->version);
        $this->info(count($this->json->tables) . ' tables will be updated: ' .
            implode(', ', $this->json->tables));
        $this->info('Download format will be: ' . $this->json->format);
        $this->line('');

        if (DB::connection()->getDriverName() == 'mysql')
            $this->info(sprintf('The SDE will be imported to mysql://%s@%s:%d/%s',
                config('database.connections.mysql.username'),
                config('database.connections.mysql.host'),
                config('database.connections.mysql.port'),
                config('database.connections.mysql.database')));

        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'postgresql']))
            $this->info(sprintf('The SDE will be imported to pgsql://%s@%s:%d/%s',
                config('database.connections.pgsql.username'),
                config('database.connections.pgsql.host'),
                config('database.connections.pgsql.port'),
                config('database.connections.pgsql.database')));

        if (! $this->confirm('Does the above look OK?', true)) {

            $this->warn('Exiting');

            return $this::SUCCESS;
        }

        if (! $this->isStorageOk()) {

            $this->error('Storage path is not OK. Please check permissions');

            return $this::INVALID;
        }

        // Download the SDE's
        $this->getSde();

        $this->importSde();

        $this->explodeMap();

        Seat::set('installed_sde', $this->json->version);

        $this->line('SDE Update Command Complete');

        // Analytics
        dispatch(new Analytics((new AnalyticsContainer)
            ->set('type', 'event')
            ->set('ec', 'queues')
            ->set('ea', 'update_sde')
            ->set('el', 'console')
            ->set('ev', $this->json->version)));

        return $this::SUCCESS;
    }

    /**
     * Query the eveseat/resources repository for SDE
     * related information.
     *
     * @return mixed
     */
    public function getJsonResource()
    {

        $result = $this->getGuzzle()->request('GET',
            $this->option('json'), [
                'headers' => ['Accept' => 'application/json'],
            ]);

        if ($result->getStatusCode() != 200)
            return json_encode([]);

        return json_decode($result->getBody());
    }

    /**
     * Get an instance of Guzzle.
     *
     * @return \GuzzleHttp\Client
     */
    public function getGuzzle()
    {

        if ($this->guzzle)
            return $this->guzzle;

        $this->guzzle = new Client();

        return $this->guzzle;

    }

    /**
     * Check that the storage path is ok. I needed it
     * will be automatically created.
     *
     * @return bool
     */
    public function isStorageOk()
    {

        $storage = storage_path() . '/sde/' . $this->json->version . '/';
        $this->info('Storage path is: ' . $storage);

        if (File::isWritable(storage_path())) {

            // Check that the path exists
            if (! File::exists($storage))
                File::makeDirectory($storage, 0755, true);

            // Set the storage path
            $this->storage_path = $storage;

            return true;

        }

        return false;
    }

    /**
     * Download the EVE Sde from Fuzzwork and save it
     * in the storage_path/sde folder.
     */
    public function getSde()
    {

        $this->line('Downloading...');

        if (DB::connection()->getDriverName() == 'mysql')
            $this->downloadMysqlSde();

        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'postgresql']))
            $this->downloadPgSqlSde();

        $this->line('');

    }

    private function downloadMysqlSde()
    {
        $bar = $this->getProgressBar(count($this->json->tables));

        foreach ($this->json->tables as $table) {

            $url = str_replace(':version', $this->json->version, $this->json->url) .
                $table . $this->json->format;
            $destination = $this->storage_path . $table . $this->json->format;

            $file_handler = fopen($destination, 'w');

            $result = $this->getGuzzle()->request('GET', $url, [
                'sink' => $file_handler, ]);

            fclose($file_handler);

            if ($result->getStatusCode() != 200)
                $this->error('Unable to download ' . $url .
                    '. The HTTP response was: ' . $result->getStatusCode());

            $bar->advance();
        }

        $bar->finish();
    }

    private function downloadPgSqlSde()
    {
        $dump_filename = str_replace('sde', 'postgres', $this->json->version) . '.dmp.bz2';
        $url = str_replace(':version', $this->json->version, $this->json->url) . $dump_filename;
        $destination = $this->storage_path . $dump_filename;

        $file_handler = fopen($destination, 'w');

        $result = $this->getGuzzle()->request('GET', $url, [
            'sink' => $file_handler, ]);

        fclose($file_handler);

        if ($result->getStatusCode() != 200)
            $this->error('Unable to download ' . $url .
                '. The HTTP response was: ' . $result->getStatusCode());
    }

    /**
     * Get a new progress bar to display based on the
     * amount of iterations we expect to use.
     *
     * @param  $iterations
     * @return \Symfony\Component\Console\Helper\ProgressBar
     */
    public function getProgressBar($iterations)
    {

        $bar = $this->output->createProgressBar($iterations);

        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% %memory:6s%');

        return $bar;
    }

    /**
     * Extract the SDE files downloaded and run the MySQL command
     * to import them into the database.
     */
    public function importSde()
    {

        $this->line('Importing...');

        if (DB::connection()->getDriverName() == 'mysql')
            $this->importMysqlSde();

        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'postgresql']))
            $this->importPgSqlSde();

        $this->line('');
    }

    private function importMysqlSde()
    {
        $bar = $this->getProgressBar(count($this->json->tables));

        foreach ($this->json->tables as $table) {

            $archive_path = $this->storage_path . $table . $this->json->format;
            $extracted_path = $this->storage_path . $table . '.sql';

            if (! File::exists($archive_path)) {

                $this->warn($archive_path . ' seems to be invalid. Skipping.');
                continue;
            }

            $this->uncompressFile($archive_path, $extracted_path);

            // With the output file ready, prepare the scary exec() command
            // that should be run. A sample $import_command is:
            // mysql -u root -h 127.0.0.1 seat < /tmp/sample.sql
            $import_command = 'mysql -u ' . config('database.connections.mysql.username') .
                // Check if the password is longer than 0. If not, don't specify the -p flag
                (strlen(config('database.connections.mysql.password')) ? ' -p' : '')
                // Append this regardless. Escape special chars in the password too.
                . escapeshellcmd(config('database.connections.mysql.password')) .
                ' -h ' . config('database.connections.mysql.host') .
                ' -P ' . config('database.connections.mysql.port') .
                ' ' . config('database.connections.mysql.database') .
                ' < ' . $extracted_path;

            // Run the command... (*scared_face*)
            exec($import_command, $output, $exit_code);

            if ($exit_code !== 0)
                $this->error('Warning: Import failed with exit code ' .
                    $exit_code . ' and command outut: ' . implode('\n', $output));

            $bar->advance();
        }

        $bar->finish();
    }

    private function importPgSqlSde()
    {
        $archive_path = $this->storage_path . str_replace('sde', 'postgres', $this->json->version) . '.dmp.bz2';
        $extracted_path = $this->storage_path . str_replace('sde', 'postgres', $this->json->version) . '.dmp';

        $this->uncompressFile($archive_path, $extracted_path);

        try {
            DB::statement('DROP OWNED BY yaml CASCADE');
        } catch (QueryException $e) {
            $this->warn('Unable to drop yaml role - role is not found.');
        }

        DB::statement('DROP ROLE IF EXISTS yaml');

        $this->info('Spawning yaml role');
        DB::statement('CREATE ROLE yaml WITH NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE INHERIT NOREPLICATION CONNECTION LIMIT -1');
        DB::statement('GRANT yaml TO seat');

        $import_command = 'PGPASSWORD=' . config('database.connections.pgsql.password') .
            ' pg_restore -d ' . config('database.connections.pgsql.database') .
            ' -h ' . config('database.connections.pgsql.host') .
            ' -p ' . config('database.connections.pgsql.port') .
            ' -U ' . config('database.connections.pgsql.username') .
            ' -t ' . implode(' -t ', $this->json->tables) .
            ' ' . $extracted_path;

        exec($import_command, $output, $exit_code);

        if ($exit_code !== 0)
            $this->error('Warning: Import failed with exit code ' .
                $exit_code . ' and command outut: ' . implode('\n', $output));
    }

    /**
     * Explode mapDenormalize table into celestial sub-tables.
     */
    private function explodeMap()
    {
        // extract regions
        DB::table('regions')->truncate();
        DB::table('regions')
            ->insertUsing([
                'region_id', 'name',
            ], DB::table('mapDenormalize')->where('groupID', MapDenormalize::REGION)
                ->select('itemID', 'itemName'));

        // extract constellations
        DB::table('constellations')->truncate();
        DB::table('constellations')
            ->insertUsing([
                'constellation_id', 'region_id', 'name',
            ], DB::table('mapDenormalize')->where('groupID', MapDenormalize::CONSTELLATION)
                ->select('itemID', 'regionID', 'itemName'));

        // extract solar systems
        DB::table('solar_systems')->truncate();
        DB::table('solar_systems')
            ->insertUsing([
                'system_id', 'constellation_id', 'region_id', 'name', 'security',
            ], DB::table('mapDenormalize')->where('groupID', MapDenormalize::SYSTEM)
                ->select('itemID', 'constellationID', 'regionID', 'itemName', 'security'));

        // extract stars
        DB::table('stars')->truncate();
        DB::table('stars')
            ->insertUsing([
                'star_id', 'system_id', 'constellation_id', 'region_id', 'name', 'type_id',
            ], DB::table('mapDenormalize')->where('groupID', MapDenormalize::SUN)
                ->select('itemID', 'solarSystemID', 'constellationID', 'regionID', 'itemName', 'typeID'));

        // extract planets
        DB::table('planets')->truncate();
        DB::table('planets')
            ->insertUsing([
                'planet_id', 'system_id', 'constellation_id', 'region_id', 'name', 'type_id',
                'x', 'y', 'z', 'radius', 'celestial_index',
            ], DB::table('mapDenormalize')->where('groupID', MapDenormalize::PLANET)
                ->select('itemID', 'solarSystemID', 'constellationID', 'regionID', 'itemName', 'typeID',
                    'x', 'y', 'z', 'radius', 'celestialIndex'));

        // extract moons
        DB::table('moons')->truncate();
        DB::table('moons')
            ->insertUsing([
                'moon_id', 'planet_id', 'system_id', 'constellation_id', 'region_id', 'name', 'type_id',
                'x', 'y', 'z', 'radius', 'celestial_index', 'orbit_index',
            ], DB::table('mapDenormalize')->where('groupID', MapDenormalize::MOON)
                ->select('itemID', 'orbitID', 'solarSystemID', 'constellationID', 'regionID', 'itemName', 'typeID',
                    'x', 'y', 'z', 'radius', 'celestialIndex', 'orbitIndex'));
    }

    private function uncompressFile($archive_path, $extracted_target_path): void
    {
        // Get 2 handles ready for both the in and out files
        $input_file = bzopen($archive_path, 'r');
        $output_file = fopen($extracted_target_path, 'w');

        // Write the $output_file in chunks
        while ($chunk = bzread($input_file, 4096))
            fwrite($output_file, $chunk, 4096);

        // Close the files
        bzclose($input_file);
        fclose($output_file);
    }
}
