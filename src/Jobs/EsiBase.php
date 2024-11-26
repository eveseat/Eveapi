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

namespace Seat\Eveapi\Jobs;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Seat\Eseye\Exceptions\RequestFailedException;
use Seat\Eveapi\Exception\PermanentInvalidTokenException;
use Seat\Eveapi\Exception\TemporaryEsiOutageException;
use Seat\Eveapi\Exception\UnavailableEveServersException;
use Seat\Eveapi\InteractsWithToken;
use Seat\Eveapi\Jobs\Middleware\CheckEsiRateLimit;
use Seat\Eveapi\Jobs\Middleware\CheckEsiRouteStatus;
use Seat\Eveapi\Jobs\Middleware\CheckServerStatus;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Services\Contracts\EsiClient;
use Seat\Services\Contracts\EsiResponse;
use Seat\Services\Helpers\AnalyticsContainer;
use Seat\Services\Jobs\Analytics;
use Throwable;

/**
 * Class EsiBase.
 *
 * @package Seat\Eveapi\Jobs
 */
abstract class EsiBase extends AbstractJob
{
    use InteractsWithToken;

    /**
     * ANTI_RACE_DELAY prevents rapid job recycling with low queue depths.
     */
    const ANTI_RACE_DELAY = 10;

    /**
     * ACCESS_TOKEN_EXPIRY_DELAY forces lock release after 25m (as access token expiry is 20m).
     */
    const ACCESS_TOKEN_EXPIRY_DELAY = 25 * 60;

    const RATE_LIMIT = 80;

    const RATE_LIMIT_DURATION = 300;

    const RATE_LIMIT_KEY = 'esiratelimit';

    const PERMANENT_INVALID_TOKEN_MESSAGES = [
        'invalid_token: The refresh token is expired.',
        'invalid_token: The refresh token does not match the client specified.',
        'invalid_grant: Invalid refresh token. Character grant missing/expired.',
        'invalid_grant: Invalid refresh token. Unable to migrate grant.',
        'invalid_grant: Invalid refresh token. Token missing/expired.',
    ];

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * @var string By default, queue all ESI jobs on public queue.
     */
    public $queue = 'public'; // By default, queue all ESI jobs on public queue.

    /**
     * The HTTP method used for the API Call.
     *
     * Eg: GET, POST, PUT, DELETE
     *
     * @var string
     */
    protected $method = '';

    /**
     * The ESI endpoint to call.
     *
     * Eg: /characters/{character_id}/
     *
     * @var string
     */
    protected $endpoint = '';

    /**
     * The endpoint version to use.
     *
     * Eg: v1, v4
     *
     * @var int
     */
    protected $version = '';

    /**
     * The SSO scope required to make the call.
     *
     * @var string
     */
    protected $scope = 'public';

    /**
     * The page to retrieve.
     *
     * Jobs that expect paged responses should have
     * this value set.
     *
     * @var int
     */
    protected $page = null;

    /**
     * The body to send along with the request.
     *
     * @var array
     */
    protected $request_body = [];

    /**
     * Any query string parameters that should be sent
     * with the request.
     *
     * @var array
     */
    protected $query_string = [];

    /**
     * @var \Seat\Eveapi\Models\RefreshToken
     */
    protected $token;

    /**
     * @var \Seat\Services\Contracts\EsiClient
     */
    protected EsiClient $esi;

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function __construct()
    {
        // Attach an ESI Client.
        $this->esi = app()->make(EsiClient::class);
    }

    /**
     * @return array
     */
    public function middleware()
    {
        return [
            new CheckEsiRateLimit,
            new CheckServerStatus,
            new CheckEsiRouteStatus,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function tags(): array
    {
        $tags = parent::tags();

        if (is_null($this->token))
            $tags[] = 'public';

        return $tags;
    }

    /**
     * @return int
     */
    public function getRateLimitKeyTtl(): int
    {
        // Redis 'default' database can be different from 'cache'
        $redis = Redis::connection('cache');

        return $redis->ttl(Cache::getPrefix() . self::RATE_LIMIT_KEY);
    }

    /**
     * @return array
     */
    public function getRoles(): array
    {
        return $this->roles ?: [];
    }

    /**
     * @return \Seat\Eveapi\Models\RefreshToken|null
     */
    public function getToken(): ?RefreshToken
    {
        return $this->token;
    }

    protected function getClient(): EsiClient
    {
        return $this->esi;
    }

    /**
     * @return string
     */
    public function getScope(): string
    {
        return $this->scope ?: '';
    }

    /**
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * @return \Illuminate\Support\Carbon
     */
    public function backoff()
    {
        return now()->addSeconds($this->attempts() * 300);
    }

    /**
     * @param  \Throwable  $exception
     *
     * @throws \Exception
     */
    public function failed(Throwable $exception)
    {
        parent::failed($exception);

        // used token is non longer valid, remove it from the system.
        if ($exception instanceof PermanentInvalidTokenException) {
            $this->token->delete();
        }

        // TQ server is not available, clear cache, so middleware will prevent to grant jobs to be processed.
        if ($exception instanceof UnavailableEveServersException) {
            cache()->remember('eve_db_status', 60, function () {
                return null;
            });
        }
    }

    /**
     * @param  int  $amount
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function incrementEsiRateLimit(int $amount = 1)
    {

        if ($this->getRateLimitKeyTtl() > 3) {

            cache()->increment(self::RATE_LIMIT_KEY, $amount);

        } else {

            cache()->set(self::RATE_LIMIT_KEY, $amount, carbon('now')
                ->addSeconds(self::RATE_LIMIT_DURATION));
        }
    }

    /**
     * @param  array  $path_values
     * @return \Seat\Services\Contracts\EsiResponse
     *
     * @throws \Seat\Eseye\Exceptions\RequestFailedException
     * @throws \Seat\Eveapi\Exception\PermanentInvalidTokenException
     * @throws \Seat\Eveapi\Exception\TemporaryEsiOutageException
     * @throws \Seat\Eveapi\Exception\UnavailableEveServersException
     * @throws \Throwable
     */
    public function retrieve(array $path_values = []): EsiResponse
    {
        $this->validateCall();

        $this->esi->setVersion($this->version);
        $this->esi->setBody($this->request_body);
        $this->esi->setQueryString($this->query_string);

        // Configure the page to get
        if (! is_null($this->page))
            $this->esi->page($this->page);

        $this->configureTokenForEsiClient();

        // Generally, we want to bubble up exceptions all the way to the
        // callee. However, in the case of this worker class, we need to
        // try and be vigilant with tokens that may have expired. So for
        // those cases we wrap in a try/catch.
        try {
            $result = $this->esi->invoke($this->method, $this->endpoint, $path_values);

            // Update the refresh token we have stored in the database.
            $this->updateRefreshToken();

        } catch (RequestFailedException $exception) {
            $this->handleEsiFailedCall($exception);
        }

        // If this is a cached load, don't bother with any further
        // processing.
        if ($result->isFromCache())
            return $result;

        // Perform error checking
        $this->warning($result);

        return $result;
    }

    /**
     * Validates a call to ensure that a method and endpoint is set
     * in the job that is using this base class.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function validateCall(): void
    {

        if (! in_array($this->method, ['get', 'post', 'put', 'patch', 'delete']))
            throw new Exception('Invalid HTTP method used');

        if (trim($this->endpoint) === '')
            throw new Exception('Empty endpoint used');

        // Enfore a version specification unless this is a 'meta' call.
        if (trim($this->version) === '' && ! in_array('meta', $this->tags()))
            throw new Exception('Version is empty');
    }

    /**
     * Logs warnings to the Eseye logger.
     *
     * These warnings will also cause analytics jobs to be
     * sent to allow for monitoring of endpoint changes.
     *
     * @param  \Seat\Services\Contracts\EsiResponse  $response
     */
    public function warning(EsiResponse $response): void
    {

        if (! is_null($response->getPagesCount()) && $this->page === null) {

            $this->esi->getLogger()->warning('Response contained pages but none was expected');

            dispatch(new Analytics((new AnalyticsContainer)
                ->set('type', 'endpoint_warning')
                ->set('ec', 'unexpected_page')
                ->set('el', $this->version)
                ->set('ev', $this->endpoint)))->onQueue('default');
        }

        if (! is_null($this->page) && $response->getPagesCount() === null) {

            $this->esi->getLogger()->warning('Expected a paged response but had none');

            dispatch(new Analytics((new AnalyticsContainer)
                ->set('type', 'endpoint_warning')
                ->set('ec', 'missing_pages')
                ->set('el', $this->version)
                ->set('ev', $this->endpoint)))->onQueue('default');
        }

        if ($response->hasHeader('warning')) {

            $this->esi->getLogger()->warning('A response contained a warning: ' .
                $response->getHeaderLine('warning'));

            dispatch(new Analytics((new AnalyticsContainer)
                ->set('type', 'generic_warning')
                ->set('ec', 'missing_pages')
                ->set('el', $this->endpoint)
                ->set('ev', $response->getHeader('Warning'))))->onQueue('default');
        }
    }

    /**
     * Check if there are any pages left in a response
     * based on the number of pages available and the
     * current page.
     *
     * @param  int|null  $pages
     * @return bool
     */
    public function nextPage(?int $pages): bool
    {

        if (is_null($pages) || $this->page >= $pages)
            return false;

        $this->page++;

        return true;
    }

    /**
     * Check if the call should respect the cache
     * based on the request being cached and settings.
     *
     * @param  \Seat\Services\Contracts\EsiResponse  $response
     * @return bool
     */
    public function shouldUseCache(EsiResponse $response): bool
    {
        return config('eveapi.cache.respect_cache') && $response->isFromCache();
    }

    /**
     * @param  \Seat\Eseye\Exceptions\RequestFailedException  $exception
     *
     * @throws \Seat\Eseye\Exceptions\RequestFailedException
     * @throws \Seat\Eveapi\Exception\PermanentInvalidTokenException
     * @throws \Seat\Eveapi\Exception\TemporaryEsiOutageException
     * @throws \Seat\Eveapi\Exception\UnavailableEveServersException|\Psr\SimpleCache\InvalidArgumentException
     */
    private function handleEsiFailedCall(RequestFailedException $exception)
    {
        // increment ESI rate limit
        $this->incrementEsiRateLimit();

        $response = $exception->getEsiResponse();

        // Update the refresh token we have stored in the database.
        $this->updateRefreshToken();

        // in case SSO did odd stuff with generated token, falsify the expires date/time
        // so eseye library will renew the token on next call.
        if ($response->getErrorCode() == 403 && $response->error() == 'token expiry is too far in the future') {
            if ($this->token) {
                $this->token->expires_on = carbon()->subMinutes(10);
                $this->token->save();
            }

            throw new TemporaryEsiOutageException($response->error(), $response->getErrorCode(), $exception);
        }

        // If the token can't login and we get an HTTP 400 together with
        // and error message stating that this is an invalid_token, remove
        // the token from SeAT.
        if ($response->getErrorCode() == 400 && in_array($response->error(), self::PERMANENT_INVALID_TOKEN_MESSAGES))
            throw new PermanentInvalidTokenException($response->error(), $response->getErrorCode(), $exception);

        if (($response->getErrorCode() == 503 && $response->error() == 'The datasource tranquility is temporarily unavailable') ||
            ($response->getErrorCode() == 504 && $response->error() == 'Timeout contacting tranquility'))
            throw new UnavailableEveServersException($response->error(), $response->getErrorCode(), $exception);

        if ($response->getErrorCode() >= 500)
            throw new TemporaryEsiOutageException($response->error(), $response->getErrorCode(), $exception);

        // Rethrow the exception
        throw $exception;
    }
}
