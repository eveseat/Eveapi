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

use Seat\Eveapi\Jobs\EsiBase;
use Seat\Eveapi\Jobs\Universe\Structures\StructureBatch;
use Seat\Eveapi\Models\Market\MarketOrder;

/**
 * Class Orders.
 *
 * @package Seat\Eveapi\Jobs\Market
 */
class Orders extends EsiBase
{

    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/markets/{region_id}/orders/';

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
        $job_start_time = now();

        // the region_id cached to speed up execution of the loop
        $region_id = setting('market_prices_region_id', true) ?: History::THE_FORGE;

        $structure_batch = new StructureBatch();

        //load all market data
        do {
            //retrieve one page of market orders
            $response = $this->retrieve(['region_id' => $region_id]);
            $orders = $response->getBody();

            // map the ESI format to the database format
            // if the batch size is increased to 1000, it crashed
            collect($orders)->chunk(100)->each(function ($chunk) use ($structure_batch) {
                // map the ESI format to the database format
                $records = $chunk->map(function ($order) use ($structure_batch) {
                    $issued = carbon($order->issued);

                    $structure_batch->addStructure($order->location_id);

                    return [
                        'order_id' => $order->order_id,
                        'duration' => $order->duration,
                        'is_buy_order' => $order->is_buy_order,
                        'issued' => $issued,
                        'expiry' => $issued->addDays($order->duration),
                        'location_id' => $order->location_id,
                        'min_volume' => $order->min_volume,
                        'price' => $order->price,
                        'range' => $order->range,
                        'system_id' => $order->system_id,
                        'type_id' => $order->type_id,
                        'volume_remaining' => $order->volume_remain,
                        'volume_total' => $order->volume_total,
                    ];
                });

                // update data in the db
                MarketOrder::upsert($records->toArray(), [
                    'order_id',
                ], [
                    'duration',
                    'is_buy_order',
                    'issued',
                    'location_id',
                    'min_volume',
                    'price',
                    'range',
                    'system_id',
                    'type_id',
                    'volume_remaining',
                    'volume_total',
                    'expiry',
                ]);
            });

            // if there are more pages with orders, continue loading them
        } while ($this->nextPage($response->getPagesCount()));

        // remove old orders
        // if they didn't get updated, we can remove them
        MarketOrder::where('updated_at', '<=', $job_start_time)->delete();

        // This is a public job, but we require a token. since we only have public citadels on the order endpoint, we can use any character
        $structure_batch->submitJobs();
    }
}
