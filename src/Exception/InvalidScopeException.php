<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015, 2016, 2017, 2018, 2019  Leon Jacobs
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

namespace Seat\Eveapi\Exception;

use Exception;
use Throwable;

/**
 * Class InvalidScopeException.
 * @package Seat\Eveapi\Exception
 */
class InvalidScopeException extends Exception
{
    /**
     * InvalidScopeException constructor.
     *
     * @param string $required_scope
     * @param array $token_scopes
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $required_scope, array $token_scopes, $code = 0, Throwable $previous = null)
    {
        $message = sprintf('The job require %s scope but provided token is only granting %s.',
            $required_scope, implode(', ', $token_scopes));

        parent::__construct($message, $code, $previous);
    }
}
