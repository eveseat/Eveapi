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

namespace Seat\Eveapi\Tests\Mocks\Esi;

use Exception;
use Seat\Eseye\Containers\EsiAuthentication;
use Seat\Eseye\Containers\EsiResponse;
use Seat\Eseye\Exceptions\RequestFailedException;
use Seat\Eseye\Fetchers\FetcherInterface;

/**
 * Class EsiFetcher.
 * @package Seat\Eveapi\Tests\Mocks\Esi
 */
class EsiMockFetcher implements FetcherInterface
{
    /**
     * @var \Seat\Eseye\Containers\EsiAuthentication
     */
    private $authentication;

    /**
     * @var array
     */
    private $scopes;

    /**
     * @var array
     */
    private static $stack = [];

    /**
     * EsiFetcher constructor.
     * @param \Seat\Eseye\Containers\EsiAuthentication|null $authentication
     */
    public function __construct(EsiAuthentication $authentication = null)
    {
        $this->authentication = $authentication;
        $this->scopes = ['public'];

        if (! is_null($authentication))
            $this->scopes = empty($authentication->offsetGet('scopes')) ? ['public'] : $authentication->offsetGet('scopes');
    }

    /**
     * @param \Seat\Eseye\Containers\EsiResponse $response
     */
    public static function add(EsiResponse $response)
    {
        array_push(static::$stack, $response);
    }

    /**
     * @inheritDoc
     */
    public function call(string $method, string $uri, array $body, array $headers = []): EsiResponse
    {
        $response = array_pop(static::$stack);

        if ($response->getErrorCode() >= 400) {
            $exception = new Exception('Dummy Exception');

            throw new RequestFailedException($exception, $response);
        }

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function getAuthenticationScopes(): array
    {
        return $this->scopes;
    }

    /**
     * @param array $scopes
     */
    public function setAuthenticationScopes(array $scopes)
    {
        $this->scopes = $scopes;
    }
}