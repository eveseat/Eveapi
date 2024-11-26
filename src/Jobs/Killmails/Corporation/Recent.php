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

namespace Seat\Eveapi\Jobs\Killmails\Corporation;

use Illuminate\Support\Facades\Bus;
use Seat\Eveapi\Jobs\AbstractAuthCorporationJob;
use Seat\Eveapi\Jobs\Killmails\Detail;
use Seat\Eveapi\Models\Killmails\Killmail;
use Seat\Eveapi\Models\Killmails\KillmailDetail;
use Seat\Eveapi\Models\RefreshToken;

/**
 * Class Recent.
 *
 * @package Seat\Eveapi\Jobs\Killmails\Corporation
 */
class Recent extends AbstractAuthCorporationJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/corporations/{corporation_id}/killmails/recent/';

    /**
     * @var int
     */
    protected $version = 'v1';

    /**
     * @var string
     */
    protected $scope = 'esi-killmails.read_corporation_killmails.v1';

    /**
     * @var array
     */
    protected $roles = ['Director'];

    /**
     * @var array
     */
    protected $tags = ['corporation', 'killmail'];

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $killmail_jobs;

    public function __construct(int $corporation_id, RefreshToken $token)
    {
        parent::__construct($corporation_id, $token);

        $this->killmail_jobs = collect();
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function handle()
    {
        do {

            $response = $this->retrieve([
                'corporation_id' => $this->getCorporationId(),
            ]);

            $killmails = $response->getBody();

            collect($killmails)->each(function ($killmail) {

                Killmail::firstOrCreate([
                    'killmail_id' => $killmail->killmail_id,
                ], [
                    'killmail_hash' => $killmail->killmail_hash,
                ]);

                if (! KillmailDetail::find($killmail->killmail_id))
                    $this->killmail_jobs->add(new Detail($killmail->killmail_id, $killmail->killmail_hash));
            });

        } while ($this->nextPage($response->getPagesCount()));

        if ($this->killmail_jobs->isNotEmpty()) {
            if($this->batchId) {
                $this->batch()->add($this->killmail_jobs->toArray());
            } else {
                Bus::batch($this->killmail_jobs->toArray())
                    ->name(sprintf('KM: %s', $this->token->character->name ?? $this->token->character_id))
                    ->onQueue($this->job->getQueue())
                    ->dispatch();
            }
        }
    }
}
