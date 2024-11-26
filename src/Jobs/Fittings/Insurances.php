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

namespace Seat\Eveapi\Jobs\Fittings;

use Seat\Eveapi\Jobs\EsiBase;
use Seat\Eveapi\Models\Fittings\Insurance;

/**
 * Class Fittings.
 *
 * @package Seat\Eveapi\Jobs\FIttings\Character
 */
class Insurances extends EsiBase
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/insurance/prices/';

    /**
     * @var string
     */
    protected $version = 'v1';

    /**
     * @var array
     */
    protected $tags = ['public'];

    /**
     * Execute the job.
     *
     * @throws \Throwable
     */
    public function handle()
    {
        $response = $this->retrieve();

        if ($this->shouldUseCache($response) && Insurance::exists())
            return;

        $insurances = $response->getBody();

        collect($insurances)->each(function ($insurance) {

            collect($insurance->levels)->each(function ($level) use ($insurance) {

                Insurance::firstOrNew([
                    'type_id' => $insurance->type_id,
                    'name' => $level->name,
                ], [
                    'cost' => $level->cost,
                    'payout' => $level->payout,
                ])->save();

            });
        });
    }
}
