<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to 2022 Leon Jacobs
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

namespace Seat\Eveapi\Mapping\Sde;

use Illuminate\Database\Eloquent\Model;
use Seat\Eveapi\Models\Sde\InvControlTowerResource;

/**
 * InvControlTowerResourceMapping.
 *
 * Used to import csv data into invControlTowerResources table.
 * CSV file must be formatted using Fuzzwork format.
 *
 * @url https://www.fuzzwork.co.uk
 */
class InvControlTowerResourceMapping extends AbstractFuzzworkMapping
{
    /**
     * @param  array  $row
     * @return Model|Model[]|null
     */
    public function model(array $row)
    {
        return (new InvControlTowerResource([
            'controlTowerTypeID' => $row[0],
            'resourceTypeID'     => $row[1],
            'purpose'            => $row[2],
            'quantity'           => $row[3],
            'minSecurityLevel'   => $row[4],
            'factionID'          => $row[5],
        ]))->bypassReadOnly();
    }
}
