<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015, 2016, 2017  Leon Jacobs
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

namespace Seat\Eveapi\Api;

use Seat\Eveapi\Exception\InvalidScopeException;
use Seat\Eveapi\Models\Eve\ApiKey;
use Seat\Eveapi\Models\JobLog;
use Seat\Eveapi\Traits\Validation;

/**
 * This abstract contains the call contract that
 * needs to be used by all update workers making
 * use of this classes concrete functions.
 *
 * Class Base
 * @package Seat\Eveapi\Api
 */
abstract class Base
{
    use Validation;

    /**
     * @var mixed|null
     */
    protected $pheal_instance = null;

    /**
     * @var null
     */
    protected $pheal = null;

    /**
     * @var null
     */
    protected $api_info = null;

    /**
     * @var null
     */
    protected $key_id = null;

    /**
     * @var null
     */
    protected $v_code = null;

    /**
     * @var null
     */
    protected $scope = null;

    /**
     * @var null
     */
    protected $corporationID = null;

    /**
     * @var null
     */
    protected $logger = null;

    /**
     * Setup the updater instance.
     */
    public function __construct()
    {

        // Resolve the configured instance out of the IoC
        $this->pheal_instance = app()
            ->make('Seat\Eveapi\Helpers\PhealSetup');

    }

    /**
     * Sets the API credentials to use with API requests.
     *
     * @param \Seat\Eveapi\Models\Eve\ApiKey $api_info
     *
     * @return $this
     * @throws \Seat\Eveapi\Exception\InvalidKeyPairException
     * @throws \Seat\Eveapi\Exception\MissingKeyPairException
     */
    public function setApi(ApiKey $api_info)
    {

        $this->validateKeyPair(
            $api_info->key_id,
            $api_info->v_code
        );

        // Set the key_id & v_code properties
        $this->key_id = $api_info->key_id;
        $this->v_code = $api_info->v_code;

        // Set the ApiKey Object
        $this->api_info = $api_info;

        return $this;
    }

    /**
     * Configure the scope for which API calls will
     * be made.
     *
     * @param $scope
     *
     * @return $this
     */
    public function setScope($scope)
    {

        $this->validateScope($scope);
        $this->scope = $scope;

        return $this;
    }

    /**
     * Get a PhealNG instance. This method will prepare
     * the authentication details based on the properties
     * and return a ready to use Object.
     *
     * @return null|\Pheal\Pheal
     */
    public function getPheal()
    {

        // Setup the Pheal instance with the key
        $this->pheal = $this->pheal_instance
            ->getPheal($this->key_id, $this->v_code);

        // Give Pheal the key type and accessMask
        // information if we have it. This will be
        // used by the access checking logic.
        if ($this->api_info) {

            // 'Refresh' the api_info as the accessMask
            // may have changed
            $this->api_info->load('info');

            if ($this->api_info->info)
                $this->pheal->setAccess(
                    $this->api_info->info->type,
                    $this->api_info->info->accessMask);
        }

        // Check if a scope was set.
        if (! is_null($this->scope))
            $this->pheal->scope = $this->scope;

        return $this->pheal;

    }

    /**
     * Gets the CorporationID from this object.
     *
     * @return null
     */
    public function getCorporationID()
    {

        return $this->corporationID;
    }

    /**
     * Sets the corporationID to use in Corporation
     * related API update work.
     *
     * @return $this
     * @throws \Seat\Eveapi\Exception\InvalidScopeException
     */
    public function setCorporationID()
    {

        if ($this->scope != 'corp')
            throw new InvalidScopeException(
                'This method only supports calls to the corp scope.');

        $this->corporationID = $this->api_info
            ->characters()->first()->corporationID;

        return $this;
    }

    /**
     * Write a new entry to a keys joblog.
     *
     * @param string $type
     * @param string $message
     */
    public function writeJobLog(string $type, string $message)
    {

        // Ensure that the joblog is enabled first
        if (! config('eveapi.config.enable_joblog'))
            return;

        if ($this->api_info)
            $this->api_info->job_logs()->save(new JobLog([
                    'type'    => $type,
                    'message' => $message,
                ])
            );

    }

    /**
     * Cleanup actions.
     */
    public function __destruct()
    {

        $this->pheal = null;
        $this->api_info = null;
        $this->scope = null;
        $this->logger = null;

    }

    /**
     * The contract for the update call. All
     * update should at least have this function.
     *
     * @return mixed
     */
    abstract protected function call();
}
