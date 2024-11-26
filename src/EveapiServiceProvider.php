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

namespace Seat\Eveapi;

use Seat\Eveapi\Models\Character\CharacterAffiliation;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Services\AbstractSeatPlugin;

/**
 * Class EveapiServiceProvider.
 *
 * @package Seat\Eveapi
 */
class EveapiServiceProvider extends AbstractSeatPlugin
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Register commands
        $this->addCommands();

        // Inform Laravel how to load migrations
        $this->add_migrations();

        // Register SDE seeders
        $this->add_sde_seeders();

        // Update api config
        $this->configure_api();

        // Register events observers
        $this->addObservers();

        // Register events listeners
        $this->addListeners();
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {

        $this->registerDatabaseSeeders([
            \Seat\Eveapi\Database\Seeders\ScheduleSeeder::class,
            // \Seat\Eveapi\Database\Seeders\Sde\SdeSeeder::class, -- Disabled until later implemented again in services
        ]);

        $this->mergeConfigFrom(__DIR__ . '/Config/eveapi.cache.php', 'eveapi.cache');
    }

    private function addCommands()
    {
        $this->commands([
            // Buckets
            \Seat\Eveapi\Commands\Seat\Buckets\Info::class,
            \Seat\Eveapi\Commands\Seat\Buckets\Balance::class,
            \Seat\Eveapi\Commands\Seat\Buckets\Update::class,
            \Seat\Eveapi\Commands\Seat\Buckets\ListCommand::class,

            // SeAT
            \Seat\Eveapi\Commands\Seat\Cache\Clear::class,
            \Seat\Eveapi\Commands\Seat\Admin\Diagnose::class,
            \Seat\Eveapi\Commands\Seat\Admin\Maintenance::class,

            // Makes
            \Seat\Eveapi\Commands\Make\Job\Esi::class,

            // Sde
            \Seat\Eveapi\Commands\Eve\Update\Sde::class,

            // Esi
            \Seat\Eveapi\Commands\Esi\Meta\Ping::class,
            \Seat\Eveapi\Commands\Esi\Update\Characters::class,
            \Seat\Eveapi\Commands\Esi\Update\Corporations::class,
            \Seat\Eveapi\Commands\Esi\Update\Notifications::class,
            \Seat\Eveapi\Commands\Esi\Update\PublicInfo::class,
            \Seat\Eveapi\Commands\Esi\Update\Affiliations::class,
            \Seat\Eveapi\Commands\Esi\Update\Prices::class,
            \Seat\Eveapi\Commands\Esi\Update\Insurances::class,
            \Seat\Eveapi\Commands\Esi\Update\Sovereignty::class,
            \Seat\Eveapi\Commands\Esi\Update\Alliances::class,
            \Seat\Eveapi\Commands\Esi\Update\Contracts::class,
            \Seat\Eveapi\Commands\Esi\Update\Killmails::class,
            \Seat\Eveapi\Commands\Esi\Job\Dispatch::class,
            \Seat\Eveapi\Commands\Esi\Update\Status::class,
            \Seat\Eveapi\Commands\Eve\Update\Status::class,

            // SSO
            \Seat\Eveapi\Commands\Seat\Tokens\Upgrade::class,
        ]);
    }

    /**
     * Set the path for migrations which should
     * be migrated by laravel. More information:
     * https://laravel.com/docs/5.5/packages#migrations.
     */
    private function add_migrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations/');
    }

    private function add_sde_seeders()
    {
        // skipped until method is added back into services.
        // $this->registerSdeSeeders([
        //     \Seat\Eveapi\Database\Seeders\Sde\MapDenormalizeSeeder::class,
        //     \Seat\Eveapi\Database\Seeders\Sde\DgmTypeAttributesSeeder::class,
        //     \Seat\Eveapi\Database\Seeders\Sde\InvControlTowerResourcesSeeder::class,
        //     \Seat\Eveapi\Database\Seeders\Sde\InvGroupsSeeder::class,
        //     \Seat\Eveapi\Database\Seeders\Sde\InvMarketGroupsSeeder::class,
        //     \Seat\Eveapi\Database\Seeders\Sde\InvTypesSeeder::class,
        //     \Seat\Eveapi\Database\Seeders\Sde\InvTypeMaterialsSeeder::class,
        //     \Seat\Eveapi\Database\Seeders\Sde\RamActivitiesSeeder::class,
        //     \Seat\Eveapi\Database\Seeders\Sde\StaStationsSeeder::class,
        // ]);
    }

    /**
     * Update Laravel 5 Swagger annotation path.
     */
    private function configure_api()
    {
        $this->registerApiAnnotationsPath([
            __DIR__ . '/Models',
        ]);
    }

    /**
     * Register the custom model observers.
     */
    private function addObservers()
    {
        CharacterAffiliation::observe(\Seat\Eveapi\Observers\CharacterAffiliationObserver::class);
        RefreshToken::observe(\Seat\Eveapi\Observers\RefreshTokenObserver::class);
    }

    /**
     * Register the custom event listeners.
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    private function addListeners()
    {
        $events = $this->app->make(\Illuminate\Contracts\Events\Dispatcher::class);

        $events->listen(\Illuminate\Queue\Events\JobExceptionOccurred::class, \Seat\Eveapi\Listeners\EsiFailedCall::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getChangelogUri(): ?string
    {
        return 'https://api.github.com/repos/eveseat/eveapi/releases';
    }

    /**
     * {@inheritdoc}
     */
    public function getChangelogBodyAttribute(): ?string
    {
        return 'body';
    }

    /**
     * {@inheritdoc}
     */
    public function getChangelogTagAttribute(): ?string
    {
        return 'tag_name';
    }

    /**
     * Return the plugin public name as it should be displayed into settings.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'SeAT Eve API';
    }

    /**
     * Return the plugin repository address.
     *
     * @return string
     */
    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/eveseat/eveapi';
    }

    /**
     * Return the plugin technical name as published on package manager.
     *
     * @return string
     */
    public function getPackagistPackageName(): string
    {
        return 'eveapi';
    }

    /**
     * Return the plugin vendor tag as published on package manager.
     *
     * @return string
     */
    public function getPackagistVendorName(): string
    {
        return 'eveseat';
    }
}
