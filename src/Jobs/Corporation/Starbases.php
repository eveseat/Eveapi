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
use Seat\Eveapi\Mapping\Structures\StarbaseMapping;
use Seat\Eveapi\Models\Corporation\CorporationStarbase;
use Seat\Eveapi\Models\RefreshToken;

/**
 * Class Starbases.
 *
 * @package Seat\Eveapi\Jobs\Corporation
 */
class Starbases extends AbstractAuthCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/starbases/';

    /**
     * @var string
     */
    protected $version = 'v2';

    /**
     * @var string
     */
    protected $scope = 'esi-corporations.read_starbases.v1';

    /**
     * @var array
     */
    protected $roles = ['Director'];

    /**
     * @var array
     */
    protected $tags = ['corporation', 'structure'];

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $known_starbases;

    /**
     * Starbases constructor.
     *
     * @param  int  $corporation_id
     * @param  \Seat\Eveapi\Models\RefreshToken  $token
     */
    public function __construct(int $corporation_id, RefreshToken $token)
    {
        $this->known_starbases = collect();

        parent::__construct($corporation_id, $token);
    }

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

            $starbases = $response->getBody();

            collect($starbases)->each(function ($starbase) {

                $model = CorporationStarbase::firstOrNew([
                    'corporation_id' => $this->getCorporationId(),
                    'starbase_id' => $starbase->starbase_id,
                ]);

                StarbaseMapping::make($model, $starbase, [
                    'corporation_id' => function () {
                        return $this->getCorporationId();
                    },
                ])->save();

                $this->known_starbases->push($starbase->starbase_id);

            });

        } while ($this->nextPage($response->getPagesCount()));

        CorporationStarbase::where('corporation_id', $this->getCorporationId())
            ->whereNotIn('starbase_id', $this->known_starbases->flatten()->all())
            ->delete();
    }
}
