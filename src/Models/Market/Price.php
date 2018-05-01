<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015, 2016, 2017, 2018  Leon Jacobs
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

namespace Seat\Eveapi\Models\Market;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Seat\Eveapi\Models\Sde\InvType;

/**
 * Class Price.
 * @package Seat\Eveapi\Models\Market
 */
class Price extends Model
{

    /**
     * @var bool
     */
    protected static $unguarded = true;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $primaryKey = 'type_id';

    /**
     * @var string
     */
    protected $table = 'market_prices';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function type()
    {

        return $this->hasOne(InvType::class, 'typeID', 'type_id');
    }

    /**
     * @param array $rows
     * @return mixed
     */
    public static function updateOrInsert(array $rows)
    {
        $table = DB::getTablePrefix().with(new self)->getTable();

        $first = reset($rows);

        $columns = implode(', ', array_map(function($value) { return "$value"; }, array_keys($first)));

        $values = implode(', ', array_map(function ($row) {
            return '(' . implode(', ', array_map(function ($value) {
                if (is_null ($value))
                    return 'null';
                return '"' . str_replace('"', '""', $value) . '"';
            }, $row)) . ')';
        }, $rows));

        $updates = implode(', ', array_map(function ($value) {
            if ($value == 'created_at')
                return 'created_at = created_at';
            return "$value = VALUES($value)";
        }, array_keys($first)));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES {$values} ON DUPLICATE KEY UPDATE {$updates}";

        return DB::statement($sql);
    }
}
