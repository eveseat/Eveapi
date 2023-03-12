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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Class RemoveCorporationCustomsOfficesSurrogateKey.
 */
class RemoveCorporationCustomsOfficesSurrogateKey extends Migration
{
    public function up()
    {
        Schema::table('corporation_customs_offices', function (Blueprint $table) {
            $table->dropPrimary(['corporation_id', 'office_id']);
        });

        Schema::table('corporation_customs_offices', function (Blueprint $table) {
            $table->bigIncrements('id')->first();
        });

        DB::statement('DELETE a FROM corporation_customs_offices a INNER JOIN corporation_customs_offices b WHERE a.id < b.id AND a.office_id = b.office_id');

        Schema::table('corporation_customs_offices', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        Schema::table('corporation_customs_offices', function (Blueprint $table) {
            $table->primary('office_id');
        });
    }

    public function down()
    {
        Schema::table('corporation_customs_offices', function (Blueprint $table) {
            $table->dropPrimary('office_id');
        });

        Schema::table('corporation_customs_offices', function (Blueprint $table) {
            $table->primary(['corporation_id', 'office_id']);
        });
    }
}
