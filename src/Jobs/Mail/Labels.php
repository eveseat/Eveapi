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

namespace Seat\Eveapi\Jobs\Mail;

use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Models\Mail\MailLabel;

/**
 * Class Labels.
 *
 * @package Seat\Eveapi\Jobs\Mail
 */
class Labels extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/mail/labels/';

    /**
     * @var int
     */
    protected $version = 'v3';

    /**
     * @var string
     */
    protected $scope = 'esi-mail.read_mail.v1';

    /**
     * @var array
     */
    protected $tags = ['mail'];

    /**
     * Execute the job.
     *
     * @throws \Throwable
     */
    public function handle()
    {
        parent::handle();

        $response = $this->retrieve([
            'character_id' => $this->getCharacterId(),
        ]);

        if ($this->shouldUseCache($response) && MailLabel::where('character_id', $this->getCharacterId())->exists())
            return;

        $labels = $response->getBody();

        collect($labels->labels)->each(function ($label) {

            // Create the label
            MailLabel::firstOrCreate([
                'character_id' => $this->getCharacterId(),
                'label_id' => $label->label_id,
            ]);

            // Update the name and color for the label
            MailLabel::where('character_id', $this->getCharacterId())
                ->where('label_id', $label->label_id)
                ->update([
                    'name' => $label->name,
                    'color' => $label->color,
                ]);
        });
    }
}
