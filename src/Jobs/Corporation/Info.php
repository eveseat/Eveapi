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
use Seat\Eveapi\Mapping\Corporations\InfoMapping;
use Seat\Eveapi\Models\Corporation\CorporationInfo;

/**
 * Class Info.
 *
 * @package Seat\Eveapi\Jobs\Corporation
 */
class Info extends AbstractCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/';

    /**
     * @var string
     */
    protected $version = 'v5';

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
            CorporationInfo::where('corporation_id', $this->getCorporationId())->exists())
            return;

        $corporation = $response->getBody();

        $model = CorporationInfo::firstOrNew([
            'corporation_id' => $this->getCorporationId(),
        ]);

        InfoMapping::make($model, $corporation, [
            'corporation_id' => function () {
                return $this->getCorporationId();
            },
        ])->save();
    }
}
