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
use Seat\Eveapi\Models\Corporation\CorporationStanding;

/**
 * Class Standings.
 *
 * @package Seat\Eveapi\Jobs\Corporation
 */
class Standings extends AbstractAuthCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/standings/';

    /**
     * @var int
     */
    protected $version = 'v2';

    /**
     * @var string
     */
    protected $scope = 'esi-corporations.read_standings.v1';

    /**
     * @var array
     */
    protected $tags = ['corporation'];

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

        do {

            $response = $this->retrieve([
                'corporation_id' => $this->getCorporationId(),
            ]);

            $standings = $response->getBody();

            collect($standings)->chunk(100)->each(function ($chunk) {

                $records = $chunk->map(function ($standing, $key) {
                    return [
                        'corporation_id' => $this->getCorporationId(),
                        'from_type' => $standing->from_type,
                        'from_id' => $standing->from_id,
                        'standing' => $standing->standing,
                        'created_at' => carbon(),
                        'updated_at' => carbon(),
                    ];
                });

                CorporationStanding::upsert($records->toArray(), [
                    'corporation_id',
                    'from_id',
                ]);
            });

        } while ($this->nextPage($response->getPagesCount()));
    }
}
