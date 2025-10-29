<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\ResponseHandlers;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Upmind\ProvisionProviders\SoftwareLicenses\Exceptions\CannotParseResponse;
use Upmind\ProvisionProviders\SoftwareLicenses\Exceptions\OperationFailed;

/**
 * Handler to parse data from a PSR-7 response body.
 */
abstract class AbstractHandler
{
    /**
     * @var \Psr\Http\Message\ResponseInterface $response
     */
    protected $response;

    /**
     * Raw response body text.
     *
     * @var string|null $body
     */
    protected $body;

    /**
     * Parsed response body data.
     *
     * @var array|null $data
     */
    protected $data;

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * Get parsed response body data.
     *
     * @return mixed|null
     */
    public function getData(?string $property = null)
    {
        $this->parse();

        if ($property) {
            return Arr::get((array)$this->data, $property);
        }

        return $this->data;
    }

    /**
     * Get trimmed response body as a string.
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->body ?? ($this->body = trim($this->response->getBody()->__toString()));
    }

    /**
     * @throws CannotParseResponse If response is an error or body is invalid
     * @throws OperationFailed If response doesn't indicate that the operation succeeded
     */
    protected function parse(): void
    {
        if (isset($this->data)) {
            // already parsed
            return;
        }

        $this->assertHttpSuccess();

        // HTTP 204 No Content responses are expected to have an empty body
        if ($this->response->getStatusCode() === 204) {
            $this->data = null;
            $this->assertResponseSuccess();
            return;
        }

        if ($this->bodyIsJson()) {
            $this->parseJson();
        } elseif ($this->bodyIsText()) {
            $this->parseText();
        } else {
            throw new CannotParseResponse('Unable to parse response of this content type');
        }

        $this->assertResponseSuccess();
    }

    /**
     * Attempt to parse the response body JSON into a data array.
     *
     * @throws CannotParseResponse
     *
     * @return void
     */
    protected function parseJson(): void
    {
        if (!$data = json_decode($this->getBody(), true)) {
            throw new CannotParseResponse('Invalid JSON response');
        }

        $this->data = $data;
    }

    /**
     * Attempt to parse the response body text into a data array.
     *
     * @throws CannotParseResponse
     *
     * @return void
     */
    protected function parseText(): void
    {
        if (!$body = $this->getBody()) {
            throw new CannotParseResponse('Empty text response');
        }

        parse_str($body, $data);

        $this->data = $data;
    }

    /**
     * Determine whether the given response is JSON.
     *
     * @return bool
     */
    protected function bodyIsJson(): bool
    {
        $contentType = $this->response->getHeaderLine('Content-Type');

        return Str::contains($contentType, ['application/json', '+json']);
    }

    /**
     * Determine whether the given response is plaintext.
     *
     * @return bool
     */
    protected function bodyIsText(): bool
    {
        $contentType = $this->response->getHeaderLine('Content-Type');

        return empty(trim($contentType))
            || Str::contains($contentType, ['text/html', 'text/plain', 'application/x-www-form-urlencoded']);
    }

    /**
     * Determine if the http response code is 2xx.
     */
    public function isHttpSuccess(): bool
    {
        $httpCode = $this->response->getStatusCode();

        return $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * Determine if the http response code is not 2xx.
     */
    public function isHttpError(): bool
    {
        return !$this->isHttpSuccess();
    }

    /**
     * @throws CannotParseResponse If response http code is not 2xx
     */
    public function assertHttpSuccess(): void
    {
        if ($this->isHttpError()) {
            throw new CannotParseResponse(
                sprintf(
                    'Service error: %s %s',
                    $this->response->getStatusCode(),
                    $this->response->getReasonPhrase()
                )
            );
        }
    }

    /**
     * Assert that the response data indicates success.
     *
     * @throws OperationFailed
     */
    abstract public function assertResponseSuccess(): void;
}
