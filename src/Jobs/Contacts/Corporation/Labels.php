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

namespace Seat\Eveapi\Jobs\Contacts\Corporation;

use Seat\Eveapi\Jobs\AbstractAuthCorporationJob;
use Seat\Eveapi\Models\Contacts\CorporationLabel;

/**
 * Class Labels.
 *
 * @package Seat\Eveapi\Jobs\Contacts\Corporation
 */
class Labels extends AbstractAuthCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/contacts/labels/';

    /**
     * @var string
     */
    protected $version = 'v1';

    /**
     * @var string
     */
    protected $scope = 'esi-corporations.read_contacts.v1';

    /**
     * @var array
     */
    protected $tags = ['corporation', 'contact'];

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function handle()
    {
        parent::handle();

        $response = $this->retrieve([
            'corporation_id' => $this->getCorporationId(),
        ]);

        if ($this->shouldUseCache($response) &&
        CorporationLabel::where('corporation_id', $this->getCorporationId())->exists())
            return;

        $labels = $response->getBody();

        collect($labels)->each(function ($label) {

            CorporationLabel::firstOrNew([
                'corporation_id' => $this->getCorporationId(),
                'label_id' => $label->label_id,
            ])->fill([
                'name' => $label->label_name,
            ])->save();
        });

        CorporationLabel::where('corporation_id', $this->getCorporationId())
            ->whereNotIn('label_id', collect($labels)->pluck('label_id')->flatten()->all())
            ->delete();
    }
}
