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

namespace Seat\Eveapi\Jobs\Industry\Corporation\Mining;

use Seat\Eveapi\Jobs\AbstractAuthCorporationJob;
use Seat\Eveapi\Models\Industry\CorporationIndustryMiningObserver;

/**
 * Class Observers.
 *
 * @package Seat\Eveapi\Jobs\Industry\Corporation\Mining
 */
class Observers extends AbstractAuthCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporation/{corporation_id}/mining/observers/';

    /**
     * @var string
     */
    protected $version = 'v1';

    /**
     * TODO: Add a local scope override as this is diff.
     *
     * @var string
     */
    protected $scope = 'esi-industry.read_corporation_mining.v1';

    /**
     * @var array
     */
    protected $roles = ['Accountant'];

    /**
     * @var array
     */
    protected $tags = ['corporation', 'industry', 'structure'];

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

        $response = $this->retrieve([
            'corporation_id' => $this->getCorporationId(),
        ]);

        if ($this->shouldUseCache($response) &&
            CorporationIndustryMiningObserver::where('corporation_id', $this->getCorporationId())->exists())
            return;

        $observers = $response->getBody();

        collect($observers)->each(function ($observer) {

            CorporationIndustryMiningObserver::firstOrNew([
                'observer_id' => $observer->observer_id,
            ])->fill([
                'corporation_id' => $this->getCorporationId(),
                'last_updated' => carbon($observer->last_updated),
                'observer_type' => $observer->observer_type,
            ])->save();
        });

        CorporationIndustryMiningObserver::where('corporation_id', $this->getCorporationId())
            ->whereNotIn('observer_id', collect($observers)
                ->pluck('observer_id')->flatten()->all())
            ->delete();
    }
}
