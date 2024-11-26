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

namespace Seat\Eveapi\Jobs\Industry\Character;

use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Jobs\Universe\Structures\StructureBatch;
use Seat\Eveapi\Mapping\Industry\JobMapping;
use Seat\Eveapi\Models\Industry\CharacterIndustryJob;

/**
 * Class Jobs.
 *
 * @package Seat\Eveapi\Jobs\Industry\Character
 */
class Jobs extends AbstractAuthCharacterJob
{
    /**
     * @var string
     */
    protected $method = 'get';

    /**
     * @var string
     */
    protected $endpoint = '/characters/{character_id}/industry/jobs/';

    /**
     * @var array
     */
    protected $query_string = [
        'include_completed' => true,
    ];

    /**
     * @var string
     */
    protected $version = 'v1';

    /**
     * @var string
     */
    protected $scope = 'esi-industry.read_character_jobs.v1';

    /**
     * @var array
     */
    protected $tags = ['character', 'industry'];

    /**
     * Execute the job.
     *
     * @throws \Throwable
     */
    public function handle()
    {
        parent::handle();
        $structure_batch = new StructureBatch();

        $response = $this->retrieve([
            'character_id' => $this->getCharacterId(),
        ]);

        if ($this->shouldUseCache($response) &&
            CharacterIndustryJob::where('character_id', $this->getCharacterId())->exists())
            return;

        $industry_jobs = $response->getBody();

        collect($industry_jobs)->each(function ($job) use ($structure_batch) {
            $structure_batch->addStructure($job->facility_id);

            $model = CharacterIndustryJob::firstOrNew([
                'character_id' => $this->getCharacterId(),
                'job_id' => $job->job_id,
            ]);

            JobMapping::make($model, $job, [
                'character_id' => function () {
                    return $this->getCharacterId();
                },
                'station_id' => function () use ($job) {
                    return $job->station_id;
                },
            ])->save();
        });

        $structure_batch->submitJobs($this->getToken());
    }
}
