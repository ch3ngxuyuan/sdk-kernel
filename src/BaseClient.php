<?php

namespace SDK\Kernel;

use GuzzleHttp\Exception\GuzzleException;
use SDK\Kernel\Contracts\AccessTokenInterface;
use SDK\Kernel\Http\Response;
use SDK\Kernel\Traits\HasHttpRequests;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LogLevel;

/**
 * Class BaseClient.
 */
class BaseClient
{
    use HasHttpRequests {
        request as protected performRequest;
    }

    /**
     * @var \SDK\Kernel\ServiceContainer
     */
    protected $app;

    /**
     * @var \SDK\Kernel\Contracts\AccessTokenInterface
     */
    protected $accessToken;

    /**
     * @var string
     */
    protected $baseUri;

    /**
     * BaseClient constructor.
     *
     * @param \SDK\Kernel\ServiceContainer $app
     * @param \SDK\Kernel\Contracts\AccessTokenInterface|null $accessToken
     */
    public function __construct(ServiceContainer $app, AccessTokenInterface $accessToken = null)
    {
        $this->app = $app;
        $this->accessToken = $accessToken ?? ($this->app['access_token'] ?? null);
    }

    /**
     * GET request.
     *
     * @param string $url
     * @param array $query
     *
     * @return \Psr\Http\Message\ResponseInterface|\SDK\Kernel\Support\Collection|array|object|string
     */
    protected function httpGet(string $url, array $query = [])
    {
        return $this->request($url, 'GET', ['query' => $query]);
    }

    /**
     * POST request.
     *
     * @param string $url
     * @param array $data
     *
     * @return \Psr\Http\Message\ResponseInterface|\SDK\Kernel\Support\Collection|array|object|string
     */
    protected function httpPost(string $url, array $data = [])
    {
        return $this->request($url, 'POST', ['form_params' => $data]);
    }

    /**
     * POST request.
     *
     * @param string $url
     * @param array $data
     *
     * @return \Psr\Http\Message\ResponseInterface|\SDK\Kernel\Support\Collection|array|object|string
     */
    protected function httpPut(string $url, array $data = [])
    {
        return $this->request($url, 'PUT', ['json' => $data]);
    }

    /**
     * POST request.
     *
     * @param string $url
     * @param array $data
     *
     * @return \Psr\Http\Message\ResponseInterface|\SDK\Kernel\Support\Collection|array|object|string
     */
    protected function httpDelete(string $url, array $data = [])
    {
        return $this->request($url, 'DELETE', ['json' => $data]);
    }

    /**
     * JSON request.
     *
     * @param string $url
     * @param array $data
     * @param array $query
     *
     * @return \Psr\Http\Message\ResponseInterface|\SDK\Kernel\Support\Collection|array|object|string
     */
    protected function httpPostJson(string $url, array $data = [], array $query = [])
    {
        return $this->request($url, 'POST', ['query' => $query, 'json' => $data]);
    }

    /**
     * Upload file.
     *
     * @param string $url
     * @param array $files
     * @param array $form
     * @param array $query
     *
     * @return \Psr\Http\Message\ResponseInterface|\SDK\Kernel\Support\Collection|array|object|string
     */
    protected function httpUpload(string $url, array $files = [], array $form = [], array $query = [])
    {
        $multipart = [];

        foreach ($files as $name => $path) {
            $multipart[] = [
                'name' => $name,
                'contents' => fopen($path, 'r'),
            ];
        }

        foreach ($form as $name => $contents) {
            $multipart[] = compact('name', 'contents');
        }

        return $this->request($url, 'POST', ['query' => $query, 'multipart' => $multipart, 'connect_timeout' => 30, 'timeout' => 30, 'read_timeout' => 30]);
    }

    /**
     * @return AccessTokenInterface
     */
    protected function getAccessToken(): AccessTokenInterface
    {
        return $this->accessToken;
    }

    /**
     * @param \SDK\Kernel\Contracts\AccessTokenInterface $accessToken
     *
     * @return $this
     */
    protected function setAccessToken(AccessTokenInterface $accessToken)
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * @param string $url
     * @param string $method
     * @param array $options
     * @param bool $returnRaw
     *
     * @return \Psr\Http\Message\ResponseInterface|\SDK\Kernel\Support\Collection|array|object|string
     *
     * @throws \SDK\Kernel\Exceptions\InvalidConfigException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function request(string $url, string $method = 'GET', array $options = [], $returnRaw = false)
    {
        if (empty($this->middlewares)) {
            $this->registerHttpMiddlewares();
        }

        try {
            $response = $this->performRequest($url, $method, $options);
        } catch (GuzzleException $e) {
            return $this->handleRequestException($e) ?? [];
        }

        return $returnRaw ? $response : $this->unwrapResponse($response);
    }

    /**
     * @param GuzzleException $e
     *
     * @throws GuzzleException
     */
    protected function handleRequestException(GuzzleException $e)
    {
        throw $e;
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return array|object|\Psr\Http\Message\ResponseInterface|string|Support\Collection
     * @throws Exceptions\InvalidConfigException
     */
    protected function unwrapResponse(\Psr\Http\Message\ResponseInterface $response)
    {
        return $this->castResponseToType($response, $this->app->config->get('response_type'));
    }

    /**
     * @param string $url
     * @param string $method
     * @param array $options
     *
     * @return \SDK\Kernel\Http\Response
     *
     * @throws \SDK\Kernel\Exceptions\InvalidConfigException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function requestRaw(string $url, string $method = 'GET', array $options = [])
    {
        return Response::buildFromPsrResponse($this->request($url, $method, $options, true));
    }

    /**
     * Register Guzzle middlewares.
     */
    protected function registerHttpMiddlewares()
    {
        // access token
        $this->pushMiddleware($this->accessTokenMiddleware(), 'access_token');
        // log
        if ($this->app->logger) {
            $this->pushMiddleware($this->logMiddleware(), 'log');
        }
    }

    /**
     * Attache access token to request query.
     *
     * @return \Closure
     */
    protected function accessTokenMiddleware()
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                if ($this->accessToken) {
                    $request = $this->accessToken->applyToRequest($request, $options);
                }

                return $handler($request, $options);
            };
        };
    }

    /**
     * Log the request.
     *
     * @return \Closure
     */
    protected function logMiddleware()
    {
        return Middleware::log(
            $this->app->logger,
            new MessageFormatter($this->app->config->get('http.log_template', MessageFormatter::DEBUG)),
            LogLevel::DEBUG
        );
    }
}