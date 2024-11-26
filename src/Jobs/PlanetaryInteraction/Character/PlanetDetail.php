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

namespace Seat\Eveapi\Jobs\PlanetaryInteraction\Character;

use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanetContent;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanetExtractor;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanetFactory;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanetHead;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanetLink;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanetPin;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanetRoute;
use Seat\Eveapi\Models\PlanetaryInteraction\CharacterPlanetRouteWaypoint;
use Seat\Eveapi\Models\RefreshToken;

/**
 * Class PlanetDetail.
 *
 * @package Seat\Eveapi\Jobs\PlanetaryInteraction\Character
 */
class PlanetDetail extends AbstractAuthCharacterJob
{

    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/planets/{planet_id}/';

    /**
     * @var string
     */
    protected $version = 'v3';

    /**
     * @var string
     */
    protected $scope = 'esi-planets.manage_planets.v1';

    /**
     * @var array
     */
    protected $tags = ['character', 'pi'];

    /**
     * @var int
     */
    private $planet_id;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $planet_pins;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $planet_factories;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $planet_extractors;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $planet_heads;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $planet_links;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $planet_waypoints;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $planet_routes;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $planet_contents;

    /**
     * PlanetDetail constructor.
     *
     * @param  RefreshToken  $token
     * @param  int  $planet_id
     */
    public function __construct(RefreshToken $token, int $planet_id)
    {
        $this->planet_id = $planet_id;

        $this->planet_pins = collect();
        $this->planet_factories = collect();
        $this->planet_extractors = collect();
        $this->planet_heads = collect();
        $this->planet_links = collect();
        $this->planet_waypoints = collect();
        $this->planet_routes = collect();
        $this->planet_contents = collect();

        parent::__construct($token);
    }

    /**
     * Execute the job.
     *
     * @throws \Throwable
     */
    public function handle()
    {
        $response = $this->retrieve([
            'character_id' => $this->getCharacterId(),
            'planet_id' => $this->planet_id,
        ]);

        if ($this->shouldUseCache($response) &&
            CharacterPlanetPin::where('character_id', $this->getCharacterId())->exists())
            return;

        $planet = $response->getBody();

        // seed database with pins
        collect($planet->pins)->each(function ($pin) {

            CharacterPlanetPin::firstOrNew([
                'character_id' => $this->getCharacterId(),
                'planet_id' => $this->planet_id,
                'pin_id' => $pin->pin_id,
            ])->fill([
                'type_id' => $pin->type_id,
                'schematic_id' => $pin->schematic_id ?? null,
                'latitude' => $pin->latitude,
                'longitude' => $pin->longitude,
                'install_time' => property_exists($pin, 'install_time') ?
                    carbon($pin->install_time) : null,
                'expiry_time' => property_exists($pin, 'expiry_time') ?
                    carbon($pin->expiry_time) : null,
                'last_cycle_start' => property_exists($pin, 'last_cycle_start') ?
                    carbon($pin->last_cycle_start) : null,
            ])->save();

            // Collect data for the cleanup phase
            $this->planet_pins->push($pin->pin_id);

            if (property_exists($pin, 'factory_details')) {

                CharacterPlanetFactory::firstOrNew([
                    'character_id' => $this->getCharacterId(),
                    'planet_id' => $this->planet_id,
                    'pin_id' => $pin->pin_id,
                ])->fill([
                    'schematic_id' => $pin->factory_details->schematic_id,
                ])->save();

                // seeding garbage collector
                $this->planet_factories->push($pin->pin_id);
            }

            if (property_exists($pin, 'extractor_details')) {

                CharacterPlanetExtractor::firstOrNew([
                    'character_id' => $this->getCharacterId(),
                    'planet_id' => $this->planet_id,
                    'pin_id' => $pin->pin_id,
                ])->fill([
                    'product_type_id' => $pin->extractor_details->product_type_id ?? null,
                    'cycle_time' => $pin->extractor_details->cycle_time ?? null,
                    'head_radius' => $pin->extractor_details->head_radius ?? null,
                    'qty_per_cycle' => $pin->extractor_details->qty_per_cycle ?? null,
                ])->save();

                // Collect data for the cleanup phase
                $this->planet_extractors->push($pin->pin_id);

                collect($pin->extractor_details->heads)->each(function ($head) use ($pin) {

                    CharacterPlanetHead::firstOrNew([
                        'character_id' => $this->getCharacterId(),
                        'planet_id' => $this->planet_id,
                        'extractor_id' => $pin->pin_id,
                        'head_id' => $head->head_id,
                    ])->fill([
                        'latitude' => $head->latitude,
                        'longitude' => $head->longitude,
                    ])->save();

                    // seeding garbage collector
                    $this->planet_heads->push([
                        'extractor_id' => $pin->pin_id,
                        'head_id' => $head->head_id,
                    ]);
                });
            }

            if (property_exists($pin, 'contents')) {

                collect($pin->contents)->each(function ($content) use ($pin) {

                    CharacterPlanetContent::firstOrNew([
                        'character_id' => $this->getCharacterId(),
                        'planet_id' => $this->planet_id,
                        'pin_id' => $pin->pin_id,
                        'type_id' => $content->type_id,
                    ])->fill([
                        'amount' => $content->amount,
                    ])->save();

                    // seeding garbage collector
                    $this->planet_contents->push([
                        'pin_id' => $pin->pin_id,
                        'type_id' => $content->type_id,
                    ]);

                });
            }
        });

        collect($planet->links)->each(function ($link) {

            CharacterPlanetLink::firstOrNew([
                'character_id' => $this->getCharacterId(),
                'planet_id' => $this->planet_id,
                'source_pin_id' => $link->source_pin_id,
                'destination_pin_id' => $link->destination_pin_id,
            ])->fill([
                'link_level' => $link->link_level,
            ])->save();

            // Collect data for the cleanup phase
            $this->planet_links->push([
                'source_pin_id' => $link->source_pin_id,
                'destination_pin_id' => $link->destination_pin_id,
            ]);

        });

        collect($planet->routes)->each(function ($route) {

            CharacterPlanetRoute::firstOrNew([
                'character_id' => $this->getCharacterId(),
                'planet_id' => $this->planet_id,
                'route_id' => $route->route_id,
            ])->fill([
                'source_pin_id' => $route->source_pin_id,
                'destination_pin_id' => $route->destination_pin_id,
                'content_type_id' => $route->content_type_id,
                'quantity' => $route->quantity,
            ])->save();

            // seeding garbage collector
            $this->planet_routes->push($route->route_id);

            if (property_exists($route, 'waypoints')) {
                collect($route->waypoints)->each(function ($waypoint) use ($route) {

                    CharacterPlanetRouteWaypoint::firstOrNew([
                        'character_id' => $this->getCharacterId(),
                        'planet_id' => $this->planet_id,
                        'route_id' => $route->route_id,
                        'pin_id' => $waypoint,
                    ])->save();

                    // seeding garbage collector
                    $this->planet_waypoints->push([
                        'route_id' => $route->route_id,
                        'pin_id' => $waypoint,
                    ]);

                });
            }
        });

        $this->planetCleanup();
    }

    /**
     * Performs a cleanup of any routes, links or contents
     * of a planet.
     *
     * @throws \Exception
     */
    private function planetCleanup()
    {

        // cleaning all waypoints
        $this->cleanRouteWaypoints();

        // cleaning all routes
        $this->cleanRoutes();

        // cleaning links
        $this->cleanLinks();

        // cleaning contents
        $this->cleanContents();

        // cleaning heads
        $this->cleanHeads();

        // cleaning extractors
        $this->cleanExtractors();

        // cleaning factories
        $this->cleanFactories();

        // cleaning pins
        $this->cleanPins();
    }

    /**
     * @param  $planet
     *
     * @throws \Exception
     */
    private function cleanRouteWaypoints()
    {
        // retrieve all waypoints which have not been returned by API.
        // We will run a delete statement on those selected rows in order to avoid any deadlock.
        $this->planet_waypoints->groupBy('route_id')->each(function ($pins) {
            $route = $pins->first();

            CharacterPlanetRouteWaypoint::where('character_id', $this->getCharacterId())
                ->where('planet_id', $this->planet_id)
                ->where('route_id', $route['route_id'])
                ->whereNotIn('pin_id', $pins->pluck('pin_id'))
                ->delete();
        });
    }

    /**
     * @throws \Exception
     */
    private function cleanRoutes()
    {
        // retrieve all routes which have not been returned by API.
        // We will run a delete statement on those selected rows in order to avoid any deadlock.
        $existing_routes = CharacterPlanetRoute::where('character_id', $this->getCharacterId())
            ->where('planet_id', $this->planet_id)
            ->whereNotIn('route_id', $this->planet_routes->toArray())
            ->get();

        CharacterPlanetRoute::where('character_id', $this->getCharacterId())
            ->where('planet_id', $this->planet_id)
            ->whereIn('route_id', $existing_routes->pluck('route_id')->toArray())
            ->delete();
    }

    /**
     * @throws \Exception
     */
    private function cleanLinks()
    {
        // retrieve all links which have not been returned by API.
        // We will run a delete statement on those selected rows in order to avoid any deadlock.
        $this->planet_links->groupBy('source_pin_id')->each(function ($links) {
            $from = $links->first();

            CharacterPlanetLink::where('character_id', $this->getCharacterId())
                ->where('planet_id', $this->planet_id)
                ->where('source_pin_id', $from['source_pin_id'])
                ->whereNotIn('destination_pin_id', $links->pluck('destination_pin_id'))
                ->delete();
        });
    }

    /**
     * @throws \Exception
     */
    private function cleanContents()
    {
        // retrieve all contents which have not been returned by API.
        // We will run a delete statement on those selected rows in order to avoid any deadlock.
        $this->planet_contents->groupBy('type_id')->each(function ($pins) {
            $type = $pins->first();

            CharacterPlanetContent::where('character_id', $this->getCharacterId())
                ->where('type_id', $type['type_id'])
                ->whereNotIn('pin_id', $pins->pluck('pin_id'))
                ->delete();
        });
    }

    /**
     * @throws \Exception
     */
    private function cleanHeads()
    {
        // retrieve all heads which have not been returned by API.
        // We will run a delete statement on those selected rows in order to avoid any deadlock.
        $this->planet_heads->groupBy('extractor_id')->each(function ($heads) {
            $extractor = $heads->first();

            CharacterPlanetHead::where('character_id', $this->getCharacterId())
                ->where('extractor_id', $extractor['extractor_id'])
                ->whereNotIn('head_id', $heads->pluck('head_id'))
                ->delete();
        });
    }

    /**
     * @throws \Exception
     */
    private function cleanExtractors()
    {
        // retrieve all extractors which have not been returned by API.
        // We will run a delete statement on those selected rows in order to avoid any deadlock.
        $existing_extractors = CharacterPlanetExtractor::where('character_id', $this->getCharacterId())
            ->where('planet_id', $this->planet_id)
            ->whereNotIn('pin_id', $this->planet_extractors->toArray())
            ->get();

        CharacterPlanetExtractor::where('character_id', $this->getCharacterId())
            ->where('planet_id', $this->planet_id)
            ->whereIn('pin_id', $existing_extractors->pluck('pin_id')->toArray())
            ->delete();
    }

    /**
     * @throws \Exception
     */
    private function cleanFactories()
    {
        // retrieve all factories which have not been returned by API.
        // We will run a delete statement on those selected rows in order to avoid any deadlock.
        $existing_factories = CharacterPlanetFactory::where('character_id', $this->getCharacterId())
            ->where('planet_id', $this->planet_id)
            ->whereNotIn('pin_id', $this->planet_factories->toArray())
            ->get();

        CharacterPlanetFactory::where('character_id', $this->getCharacterId())
            ->where('planet_id', $this->planet_id)
            ->whereIn('pin_id', $existing_factories->pluck('pin_id')->toArray())
            ->delete();
    }

    /**
     * @throws \Exception
     */
    private function cleanPins()
    {
        // retrieve all pins which have not been returned by API.
        // We will run a delete statement on those selected rows in order to avoid any deadlock.
        $existing_pins = CharacterPlanetPin::where('character_id', $this->getCharacterId())
            ->where('planet_id', $this->planet_id)
            ->whereNotIn('pin_id', $this->planet_pins->toArray())
            ->get();

        CharacterPlanetPin::where('character_id', $this->getCharacterId())
            ->where('planet_id', $this->planet_id)
            ->whereIn('pin_id', $existing_pins->pluck('pin_id')->toArray())
            ->delete();
    }
}
