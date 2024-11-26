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
use Illuminate\Support\Facades\Bus;
use Seat\Eveapi\Jobs\EsiBase;

/**
 * Loads all Type IDs for which the history is available.
 */
class DispatchHistoryJobs extends EsiBase
{
    use Batchable;

    const THE_FORGE = 10000002;

    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/markets/{region_id}/types/';

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
     * @return void
     */
    public function handle()
    {
        $region_id = setting('market_prices_region_id', true) ?: self::THE_FORGE;

        $types = collect();

        do {
            $response = $this->retrieve(['region_id' => $region_id]);
            $orders = $response->getBody();

            $types = $types->merge($orders);

            // if there are more pages with orders, continue loading them
        } while ($this->nextPage($response->getPagesCount()));

        // create history jobs
        $jobs = collect();
        $batch_jobs_count = (int) ceil($types->count() / History::ENDPOINT_RATE_LIMIT_CALLS);
        $types->chunk(History::ENDPOINT_RATE_LIMIT_CALLS)->each(function ($results, $page) use ($batch_jobs_count, $jobs) {
            $jobs->add((new History($results->toArray()))->setCurrentBatchCount($page)->setTotalBatchCount($batch_jobs_count));
        });

        if ($jobs->isEmpty()) return;

        if ($this->batchId) {
            $this->batch()->add($jobs);
        } else {
            Bus::batch($jobs)
                ->name('Market History')
                ->onQueue($this->job->getQueue())
                ->dispatch();
        }
    }
}
