<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Tests\Unit\ResponseHandlers;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\Generic\ResponseHandlers\DefaultResponseHandler;

class DefaultResponseHandlerTest extends TestCase
{
    public function testHandles204ResponseWithEmptyBody(): void
    {
        $response = new Response(204, [], '');
        $handler = new DefaultResponseHandler($response);

        // Should not throw an exception
        $handler->assertResponseSuccess();

        // getData should return empty array for 204 responses
        $this->assertNull($handler->getData());
    }

    public function testHandles204ResponseWithNoContentHeader(): void
    {
        $response = new Response(204, ['Content-Type' => 'application/json'], '');
        $handler = new DefaultResponseHandler($response);

        // Should not throw an exception
        $handler->assertResponseSuccess();

        // getData should return empty array for 204 responses
        $this->assertNull($handler->getData());
    }

    public function testHandles200ResponseWithSuccessBody(): void
    {
        $responseBody = json_encode(['success' => true]);
        $response = new Response(200, ['Content-Type' => 'application/json'], $responseBody);
        $handler = new DefaultResponseHandler($response);

        // Should not throw an exception
        $handler->assertResponseSuccess();

        // getData should return the parsed data
        $this->assertEquals(['success' => true], $handler->getData());
    }

    public function testHandles200ResponseWithResultOk(): void
    {
        $responseBody = json_encode(['result' => 'ok']);
        $response = new Response(200, ['Content-Type' => 'application/json'], $responseBody);
        $handler = new DefaultResponseHandler($response);

        // Should not throw an exception
        $handler->assertResponseSuccess();

        // getData should return the parsed data
        $this->assertEquals(['result' => 'ok'], $handler->getData());
    }
}
