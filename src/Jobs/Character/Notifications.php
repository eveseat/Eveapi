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

namespace Seat\Eveapi\Jobs\Character;

use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Mapping\Characters\NotificationMapping;
use Seat\Eveapi\Models\Character\CharacterNotification;

/**
 * Class Notifications.
 *
 * @package Seat\Eveapi\Jobs\Character
 */
class Notifications extends AbstractAuthCharacterJob
{
    public $queue = 'notifications';

    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/notifications/';

    /**
     * @var int
     */
    protected $version = 'v5';

    /**
     * @var string
     */
    protected $scope = 'esi-characters.read_notifications.v1';

    /**
     * @var array
     */
    protected $tags = ['character', 'notification'];

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

        $response = $this->retrieve([
            'character_id' => $this->getCharacterId(),
        ]);

        if ($this->shouldUseCache($response) &&
            CharacterNotification::where('character_id', $this->getCharacterId())->exists())
            return;

        $notifications = collect($response->getBody());

        $notifications->each(function ($notification) {

            $model = CharacterNotification::firstOrNew([
                'character_id' => $this->getCharacterId(),
                'notification_id' => $notification->notification_id,
            ]);

            NotificationMapping::make($model, $notification, [
                'character_id' => function () {
                    return $this->getCharacterId();
                },
                'is_read' => function () use ($notification) {
                    return isset($notification->is_read) ? $notification->is_read : false;
                },
            ])->save();
        });
    }
}
