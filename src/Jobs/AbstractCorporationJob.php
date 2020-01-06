<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to 2020 Leon Jacobs
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

namespace Seat\Eveapi\Jobs;

use Exception;

/**
 * Class AbstractCorporationJob.
 *
 * @package Seat\Eveapi\Jobs
 */
abstract class AbstractCorporationJob extends EsiBase
{
    /**
     * @var int The corporation ID to which the job is related.
     */
    private $corporation_id;

    /**
     * AbstractCorporationJob constructor.
     *
     * @param int $corporation_id
     */
    public function __construct(int $corporation_id)
    {
        $this->corporation_id = $corporation_id;

        parent::__construct();
    }

    /**
     * Get the corporation ID to which this job is related.
     *
     * @return int
     */
    public function getCorporationId(): int
    {
        return $this->corporation_id;
    }

    /**
     * {@inheritdoc}
     */
    public function tags(): array
    {
        $tags = parent::tags();

        if (! in_array('corporation', $tags))
            $tags[] = 'corporation';

        try {
            if (! in_array($this->getCorporationId(), $tags))
                $tags[] = $this->getCorporationId();
        } catch (Exception $e) {
            logger()->error($e->getMessage(), $e->getTrace());
        }

        return $tags;
    }
}
