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

use Seat\Eveapi\Jobs\AbstractCorporationJob;
use Seat\Eveapi\Models\Corporation\CorporationAllianceHistory;

/**
 * Class AllianceHistory.
 *
 * @package Seat\Eveapi\Jobs\Corporation
 */
class AllianceHistory extends AbstractCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/alliancehistory/';

    /**
     * @var string
     */
    protected $version = 'v3';

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
        CorporationAllianceHistory::where('corporation_id', $this->getCorporationId())->exists())
            return;

        $history = $response->getBody();

        collect($history)->each(function ($alliance) {

            CorporationAllianceHistory::firstOrNew([
                'corporation_id' => $this->getCorporationId(),
                'record_id' => $alliance->record_id,
            ])->fill([
                'start_date' => carbon($alliance->start_date),
                'alliance_id' => $alliance->alliance_id ?? null,
                'is_deleted' => $alliance->is_deleted ?? false,
            ])->save();

        });
    }
}
