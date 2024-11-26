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

namespace Seat\Eveapi\Jobs\Corporation;

use Seat\Eveapi\Jobs\AbstractAuthCorporationJob;
use Seat\Eveapi\Jobs\Universe\Structures\StructureBatch;
use Seat\Eveapi\Mapping\Assets\ContainerLogsMapping;
use Seat\Eveapi\Models\Corporation\CorporationContainerLog;

/**
 * Class ContainerLogs.
 *
 * @package Seat\Eveapi\Jobs\Corporation
 */
class ContainerLogs extends AbstractAuthCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/containers/logs/';

    /**
     * @var string
     */
    protected $version = 'v3';

    /**
     * @var string
     */
    protected $scope = 'esi-corporations.read_container_logs.v1';

    /**
     * @var array
     */
    protected $roles = ['Director'];

    /**
     * @var array
     */
    protected $tags = ['corporation', 'asset'];

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function handle()
    {
        parent::handle();

        $structure_batch = new StructureBatch();

        do {

            $response = $this->retrieve([
                'corporation_id' => $this->getCorporationId(),
            ]);

            if ($this->shouldUseCache($response) &&
                CorporationContainerLog::where('corporation_id', $this->getCorporationId())->exists())
                continue;

            $logs = $response->getBody();

            collect($logs)->each(function ($log) use ($structure_batch) {
                // I assume location_flag is the same as in assets
                if (in_array($log->location_flag, StructureBatch::RESOLVABLE_LOCATION_FLAGS)) {
                    $structure_batch->addStructure($log->location_id);
                }

                $model = CorporationContainerLog::firstOrNew([
                    'corporation_id' => $this->getCorporationId(),
                    'container_id' => $log->container_id,
                    'logged_at' => carbon($log->logged_at),
                ]);

                ContainerLogsMapping::make($model, $log, [
                    'corporation_id' => function () {
                        return $this->getCorporationId();
                    },
                ])->save();

            });
        }  while ($this->nextPage($response->getPagesCount()));

        $structure_batch->submitJobs($this->getToken());
    }
}
