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
use Seat\Eveapi\Models\Corporation\CorporationDivision;

/**
 * Class Divisions.
 *
 * @package Seat\Eveapi\Jobs\Corporation
 */
class Divisions extends AbstractAuthCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/divisions/';

    /**
     * @var string
     */
    protected $version = 'v2';

    /**
     * @var string
     */
    protected $scope = 'esi-corporations.read_divisions.v1';

    /**
     * @var array
     */
    protected $roles = ['Director'];

    /**
     * @var array
     */
    protected $tags = ['corporation'];

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

        $response = $this->retrieve([
            'corporation_id' => $this->getCorporationId(),
        ]);

        if ($this->shouldUseCache($response) &&
            CorporationDivision::where('corporation_id', $this->getCorporationId())->exists())
            return;

        $divisions = $response->getBody();

        if (property_exists($divisions, 'hangar'))

            collect($divisions->hangar)->each(function ($hangar) {

                CorporationDivision::firstOrNew([
                    'corporation_id' => $this->getCorporationId(),
                    'type' => 'hangar',
                    'division' => $hangar->division,
                ])->fill([
                    'name' => $hangar->name ?? null,
                ])->save();
            });

        if (property_exists($divisions, 'wallet'))

            collect($divisions->wallet)->each(function ($wallet) {

                CorporationDivision::firstOrNew([
                    'corporation_id' => $this->getCorporationId(),
                    'type' => 'wallet',
                    'division' => $wallet->division,
                ])->fill([
                    'name' => $wallet->name ?? null,
                ])->save();
            });
    }
}
