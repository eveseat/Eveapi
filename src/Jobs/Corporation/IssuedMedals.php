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
use Seat\Eveapi\Models\Corporation\CorporationIssuedMedal;

/**
 * Class IssuedMedals.
 *
 * @package Seat\Eveapi\Jobs\Corporation
 */
class IssuedMedals extends AbstractAuthCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/medals/issued/';

    /**
     * @var string
     */
    protected $version = 'v2';

    /**
     * @var string
     */
    protected $scope = 'esi-corporations.read_medals.v1';

    /**
     * @var array
     */
    protected $roles = ['Director'];

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

            if ($this->shouldUseCache($response) &&
                CorporationIssuedMedal::where('corporation_id', $this->getCorporationId())->exists())
                continue;

            $medals = $response->getBody();

            collect($medals)->each(function ($medal) {

                CorporationIssuedMedal::firstOrNew([
                    'corporation_id' => $this->getCorporationId(),
                    'medal_id' => $medal->medal_id,
                    'character_id' => $medal->character_id,
                ])->fill([
                    'reason' => $medal->reason,
                    'status' => $medal->status,
                    'issuer_id' => $medal->issuer_id,
                    'issued_at' => carbon($medal->issued_at),
                ])->save();

            });
        } while ($this->nextPage($response->getPagesCount()));
    }
}
