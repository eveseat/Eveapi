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

namespace Seat\Eveapi\Jobs\Market;

use Illuminate\Bus\Batchable;
use Seat\Eveapi\Jobs\EsiBase;
use Seat\Eveapi\Models\Market\Price;

/**
 * Class Prices.
 *
 * @package Seat\Eveapi\Jobs\Market
 */
class Prices extends EsiBase
{
    use Batchable;

    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/markets/prices/';

    /**
     * @var string
     */
    protected $version = 'v1';

    /**
     * @var array
     */
    protected $tags = ['public', 'market'];

    /**
     * Execute the job.
     *
     * @throws \Throwable
     */
    public function handle()
    {
        if ($this->batchId && $this->batch()->cancelled()) {
            logger()->debug(sprintf('[Jobs][%s] Orders - Cancelling job due to relevant batch %s cancellation.', $this->job->getJobId(), $this->batch()->id));

            return;
        }

        $response = $this->retrieve();

        if ($this->shouldUseCache($response) &&
            Price::exists())
            return;

        $prices = $response->getBody();

        collect($prices)->chunk(1000)->each(function ($chunk) {

            $records = $chunk->map(function ($item, $key) {

                return [
                    'type_id' => $item->type_id,
                    'average_price' => $item->average_price ?? 0.0,
                    'adjusted_price' => $item->adjusted_price ?? 0.0,
                    'created_at' => carbon(),
                    'updated_at' => carbon(),
                ];
            });

            Price::upsert($records->toArray(), [
                'type_id',
            ]);
        });
    }
}
