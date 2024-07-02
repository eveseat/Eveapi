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

namespace Seat\Eveapi\Commands\Seat\Buckets;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Seat\Eveapi\Bus\Character;
use Seat\Eveapi\Bus\Corporation;
use Seat\Eveapi\Models\Bucket;
use Seat\Eveapi\Models\RefreshToken;

/**
 * Class Update.
 *
 * @package Seat\Eveapi\Commands\Seat\Buckets
 */
class Update extends Command
{
    /**
     * @var string
     */
    protected $signature = 'seat:buckets:update';

    /**
     * @var string
     */
    protected $description = 'Schedule jobs from next bucket to update tokens.';

    private array $scheduled_corporations = [];

    /**
     * Execute command.
     */
    public function handle()
    {
        // retrieve the bucket which need to be processed.
        $bucket = $this->getNextBucket();

        // store bucket ID, so we keep track of the flow.
        Cache::forever('buckets:processed', $bucket->id);

        $bucket->refresh_tokens()
            ->with(['character', 'affiliation'])
            ->get()->each(function (RefreshToken $token) {
                $this->updateToken($token);
            });
    }

    private function updateToken(RefreshToken $token)
    {
        // the esi update interval in seconds, but at least an hour
        $esi_update_interval = max(60*60,$token->esi_update_interval);

        // if the token got processed in the update interval, return now
        if($token->last_esi_update !== null && $token->last_esi_update->diffInSeconds(now()) < $esi_update_interval) return;

        // update the last updated indicator
        $token->last_esi_update = now();
        $token->save();

        // schedule character related update
        (new Character($token->character_id, $token))->fire();
        logger()->debug('[Buckets] Processing token from a bucket', [
            'flow' => 'character',
            'token' => $token->character_id,
        ]);

        // if this is a director, schedule corporation related updates
        if (
            $token->affiliation->corporation_id !== null
            && $token->character->corporation_roles->where('scope', 'roles')->where('role', 'Director')->isNotEmpty()
            && !$this->isCorporationAlreadyScheduled($token->affiliation->corporation_id)
        ) {
            $this->markCorporationScheduled($token->affiliation->corporation_id);
            (new Corporation($token->affiliation->corporation_id, $token))->fire();
            logger()->debug('[Buckets] Processing token from a bucket.', [
                'flow' => 'corporation',
                'token' => $token->character_id,
            ]);
        }
    }

    /**
     * Determine what is the next bucket to process.
     *
     * @return Bucket
     */
    private function getNextBucket(): Bucket
    {
        // retrieve last processed bucket ID.
        $last_bucket = $this->getLastProcessedBucketID();

        // pickup next bucket based.
        $bucket = Bucket::where('id', '>', $last_bucket)->orderBy('id')->first();

        // in case we cannot find any bucket, restart cycle from the beginning.
        if (is_null($bucket)) {
            $bucket = Bucket::orderBy('id')->first();

            // if we're still not able to find a candidate, spawn a new bucket.
            if (!$bucket) {
                $bucket = new Bucket();
                $bucket->save();

                return $bucket;
            }
        }

        return $bucket;
    }

    /**
     * Determine from Redis which bucket has been processed lastly.
     *
     * @return int
     */
    private function getLastProcessedBucketID(): int
    {
        return Cache::get('buckets:processed') ?: 0;
    }

    private function isCorporationAlreadyScheduled(int $corporation_id): bool
    {
        return array_key_exists($corporation_id, $this->scheduled_corporations);
    }

    private function markCorporationScheduled(int $corporation_id): void
    {
        $this->scheduled_corporations[$corporation_id] = true;
    }
}
