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

namespace Seat\Eveapi\Jobs\Contacts\Alliance;

use Seat\Eveapi\Jobs\AbstractAuthAllianceJob;
use Seat\Eveapi\Models\Contacts\AllianceLabel;

/**
 * Class Labels.
 *
 * @package Seat\Eveapi\Jobs\Contacts\Alliance
 */
class Labels extends AbstractAuthAllianceJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/alliances/{alliance_id}/contacts/labels/';

    /**
     * @var string
     */
    protected $version = 'v1';

    /**
     * @var string
     */
    protected $scope = 'esi-alliances.read_contacts.v1';

    /**
     * @var array
     */
    protected $tags = ['alliance', 'contact'];

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function handle()
    {
        $response = $this->retrieve([
            'alliance_id' => $this->getAllianceId(),
        ]);

        if ($this->shouldUseCache($response) &&
            AllianceLabel::where('alliance_id', $this->getAllianceId())->exists())
            return;

        $labels = $response->getBody();

        collect($labels)->each(function ($label) {

            AllianceLabel::firstOrNew([
                'alliance_id' => $this->getAllianceId(),
                'label_id' => $label->label_id,
            ])->fill([
                'name' => $label->label_name,
            ])->save();
        });

        AllianceLabel::where('alliance_id', $this->getAllianceId())
            ->whereNotIn('label_id', collect($labels)->pluck('label_id')->flatten()->all())
            ->delete();
    }
}
