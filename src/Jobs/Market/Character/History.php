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

namespace Seat\Eveapi\Jobs\Market\Character;

use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Mapping\Financial\OrderMapping;
use Seat\Eveapi\Models\Market\CharacterOrder;

/**
 * Class History.
 *
 * @package Seat\Eveapi\Jobs\Market\Character
 */
class History extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/orders/history/';

    /**
     * @var int
     */
    protected $version = 'v1';

    /**
     * @var string
     */
    protected $scope = 'esi-markets.read_character_orders.v1';

    /**
     * @var array
     */
    protected $tags = ['character', 'market'];

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * Execute the job.
     *
     * @throws \Throwable
     */
    public function handle()
    {
        parent::handle();

        do {

            $response = $this->retrieve([
                'character_id' => $this->getCharacterId(),
            ]);

            $orders = $response->getBody();

            collect($orders)->each(function ($order) {

                $model = CharacterOrder::firstOrNew([
                    'character_id' => $this->getCharacterId(),
                    'order_id' => $order->order_id,
                ]);

                OrderMapping::make($model, $order, [
                    'character_id' => function () {
                        return $this->getCharacterId();
                    },
                    'is_corporation' => function () use ($order) {
                        return $order->is_corporation;
                    },
                    'state' => function () use ($order) {
                        return $order->state;
                    },
                ])->save();
            });
        } while ($this->nextPage($response->getPagesCount()));
    }
}
