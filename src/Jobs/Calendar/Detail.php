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

use Seat\Eseye\Exceptions\RequestFailedException;
use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Mapping\Characters\CalendarDetailMapping;
use Seat\Eveapi\Models\Calendar\CharacterCalendarEvent;
use Seat\Eveapi\Models\Calendar\CharacterCalendarEventDetail;

/**
 * Class Detail.
 *
 * @package Seat\Eveapi\Jobs\Calendar
 */
class Detail extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/calendar/{event_id}/';

    /**
     * @var string
     */
    protected $version = 'v3';

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
     * @return void
     *
     * @throws \Exception
     */
    public function handle()
    {
        parent::handle();

        $event_ids = CharacterCalendarEvent::where('character_id', $this->getCharacterId())
            ->whereNotIn('event_id', function ($query) {

                $query->select('event_id')
                    ->from('character_calendar_event_details');

            })->pluck('event_id');

        $event_ids->each(function ($event_id) {

            try {
                $response = $this->retrieve([
                    'character_id' => $this->getCharacterId(),
                    'event_id' => $event_id,
                ]);

                $model = CharacterCalendarEventDetail::firstOrNew([
                    'event_id' => $event_id,
                ]);

                if ($this->shouldUseCache($response) && $model->exists) return true; // Move onto the next detail if this is cached

                $detail = $response->getBody();

                CalendarDetailMapping::make($model, $detail, [
                    'event_id' => function () use ($event_id) {
                        return $event_id;
                    },
                ])->save();

            } catch (RequestFailedException $e) {
                if (strtolower($e->getError()) == 'event not found!') {
                    CharacterCalendarEvent::where('character_id', $this->getCharacterId())
                                          ->where('event_id', $event_id)
                                          ->delete();

                    return;
                }

                throw $e;
            }
        });
    }
}
