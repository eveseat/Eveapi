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
use Seat\Eveapi\Mapping\Structures\StarbaseDetailMapping;
use Seat\Eveapi\Models\Corporation\CorporationStarbase;
use Seat\Eveapi\Models\Corporation\CorporationStarbaseDetail;
use Seat\Eveapi\Models\Corporation\CorporationStarbaseFuel;
use Seat\Eveapi\Models\RefreshToken;

/**
 * Class Starbase.
 *
 * @package Seat\Eveapi\Jobs\Corporation
 */
class StarbaseDetails extends AbstractAuthCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/starbases/{starbase_id}/';

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
     * @var
     */
    protected $known_starbases;

    /**
     * StarbaseDetails constructor.
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

        CorporationStarbase::where('corporation_id', $this->getCorporationId())
            ->get()->each(function ($starbase) {

                $this->query_string = [
                    'system_id' => $starbase->system_id,
                ];

                $response = $this->retrieve([
                    'corporation_id' => $this->getCorporationId(),
                    'starbase_id' => $starbase->starbase_id,
                ]);

                $model = CorporationStarbaseDetail::firstOrNew([
                    'corporation_id' => $this->getCorporationId(),
                    'starbase_id' => $starbase->starbase_id,
                ]);

                if ($this->shouldUseCache($response) && $model->exists){
                        $this->known_starbases->push($starbase->starbase_id);

                        return true;
                    }

                $detail = $response->getBody();

                StarbaseDetailMapping::make($model, $detail, [
                    'corporation_id' => function () {
                        return $this->getCorporationId();
                    },
                    'starbase_id' => function () use ($starbase) {
                        return $starbase->starbase_id;
                    },
                ])->save();

                if (property_exists($detail, 'fuels'))

                    collect($detail->fuels)->each(function ($fuel) use ($starbase) {

                        CorporationStarbaseFuel::firstOrNew([
                            'corporation_id' => $this->getCorporationId(),
                            'starbase_id' => $starbase->starbase_id,
                            'type_id' => $fuel->type_id,
                        ])->fill([
                            'quantity' => $fuel->quantity,
                        ])->save();

                    });

                $this->known_starbases->push($starbase->starbase_id);

            });

        CorporationStarbaseDetail::where('corporation_id', $this->getCorporationId())
            ->whereNotIn('starbase_id', $this->known_starbases->flatten()->all())
            ->delete();

        CorporationStarbaseFuel::where('corporation_id', $this->getCorporationId())
            ->whereNotIn('starbase_id', $this->known_starbases->flatten()->all())
            ->delete();
    }
}
