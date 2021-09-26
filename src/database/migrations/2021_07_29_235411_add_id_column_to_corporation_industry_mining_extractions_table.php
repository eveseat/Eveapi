<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to 2021 Leon Jacobs
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

class AddIdColumnToCorporationIndustryMiningExtractionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('corporation_industry_mining_extractions', function (Blueprint $table) {
            $table->dropPrimary(['moon_id']);
            $table->dropIndex(['moon_id']);
        });

        Schema::table('corporation_industry_mining_extractions', function (Blueprint $table) {
            $table->bigIncrements('id')->first();
            $table->unique(['moon_id', 'extraction_start_time'], 'corporation_industry_mining_extractions_uk_moon_extraction');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        DB::statement('delete t1 from corporation_industry_mining_extractions t1 inner join corporation_industry_mining_extractions t2 where t1.id < t2.id AND t1.moon_id=t2.moon_id');

        Schema::table('corporation_industry_mining_extractions', function (Blueprint $table) {
            $table->dropUnique('corporation_industry_mining_extractions_uk_moon_extraction');
            $table->dropColumn('id');
            $table->index(['moon_id']);
            $table->primary(['moon_id']);
        });
    }
}
