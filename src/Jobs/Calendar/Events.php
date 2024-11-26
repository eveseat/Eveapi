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

namespace Seat\Eveapi\Jobs\Calendar;

use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Mapping\Characters\CalendarEventMapping;
use Seat\Eveapi\Models\Calendar\CharacterCalendarEvent;

/**
 * Class Events.
 *
 * @package Seat\Eveapi\Jobs\Calendar
 */
class Events extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/calendar/';

    /**
     * @var string
     */
    protected $version = 'v1';

    /**
     * @var string
     */
    protected $scope = 'esi-calendar.read_calendar_events.v1';

    /**
     * @var array
     */
    protected $tags = ['calendar'];

    /**
     * @var int
     */
    protected $from_id = 0;

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function handle()
    {
        parent::handle();

        // Perform a walk backwards to get all of the
        // entries as far back as possible. When the response from
        // ESI is empty, we can assume we have everything.
        while (true) {

            $this->query_string = ['from_event' => $this->from_id];

            $response = $this->retrieve([
                'character_id' => $this->getCharacterId(),
            ]);

            if ($this->shouldUseCache($response) && CharacterCalendarEvent::where('character_id', $this->getCharacterId())->exists())
                return;

            $events = collect($response->getBody());

            // if we have no more entries, break the loop.
            if ($events->count() === 0)
                break;

            $events->each(function ($event) {

                $model = CharacterCalendarEvent::firstOrNew([
                    'character_id' => $this->getCharacterId(),
                    'event_id' => $event->event_id,
                ]);

                CalendarEventMapping::make($model, $event, [
                    'character_id' => function () {
                        return $this->getCharacterId();
                    },
                ])->save();
            });

            $this->from_id = $events->max('event_id');
        }
    }
}
