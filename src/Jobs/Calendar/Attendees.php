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
use Seat\Eveapi\Models\Calendar\CharacterCalendarAttendee;
use Seat\Eveapi\Models\Calendar\CharacterCalendarEventDetail;

/**
 * Class Attendees.
 *
 * @package Seat\Eveapi\Jobs\Calendar
 */
class Attendees extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/calendar/{event_id}/attendees/';

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
     * Execute the job.
     *
     * @throws \Exception
     */
    public function handle()
    {
        parent::handle();

        $this->updateAttendees('character', $this->getCharacterId());
    }

    /**
     * TODO: Why do we have this as a separate call?
     * We should add this to handle().
     *
     * @param  string  $owner_type
     * @param  int  $owner_id
     *
     * @throws \Exception
     */
    private function updateAttendees(string $owner_type, int $owner_id)
    {

        CharacterCalendarEventDetail::where('owner_id', $owner_id)
            ->where('owner_type', $owner_type)
            ->get()->each(function ($event) {

                $response = $this->retrieve([
                    'character_id' => $this->getCharacterId(),
                    'event_id' => $event->event_id,
                ]);

                if ($this->shouldUseCache($response) && CharacterCalendarAttendee::where('event_id', $event->event_id)->exists())
                    return true; // Return true to move onto the next event

                $attendees = collect($response->getBody());

                $attendees->each(function ($attendee) use ($event) {

                    CharacterCalendarAttendee::firstOrNew([
                        'character_id' => $attendee->character_id,
                        'event_id' => $event->event_id,
                    ])->fill([
                        'event_response' => $attendee->event_response,
                    ])->save();

                });

                CharacterCalendarAttendee::where('event_id', $event->event_id)
                    ->whereNotIn('character_id', collect($response)
                        ->pluck('character_id')->flatten()->all())
                    ->delete();
            });
    }
}
