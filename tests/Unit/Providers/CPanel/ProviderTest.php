<?php

declare(strict_types=1);

namespace Unit\Providers\CPanel;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Str;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\ReissueParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\SuspendParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\CPanel\Provider;

class ProviderTest extends TestCase
{
    /**
     * @var Client|MockObject
     */
    private Client $client;

    /**
     * @var Provider|MockObject
     */
    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(Client::class);

        $this->provider = $this->getMockBuilder(Provider::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['client'])
            ->getMock();

        $this->provider->method('client')->willReturn($this->client);
    }

    public function testReissueIsNotSupported(): void
    {
        $this->expectException(ProvisionFunctionError::class);

        $reissueParams = $this->createMock(ReissueParams::class);

        $this->provider->reissue($reissueParams);
    }

    public function testReissueIsNotSupportedMessage(): void
    {
        $this->expectExceptionMessage('Operation not supported');

        $reissueParams = $this->createMock(ReissueParams::class);

        $this->provider->reissue($reissueParams);
    }

    /**
     * @throws \Throwable
     */
    public function testCannotSuspendInactiveLicense(): void
    {
        $suspendParams = $this->createMock(SuspendParams::class);

        $licenseKey = Str::random(6);

        $suspendParams->expects($this->once())
            ->method('__get')
            ->with('license_key')
            ->willReturn($licenseKey);

        $responseData = [
            'licenses' => [
                'L' . $licenseKey => [
                    'status' => $this->getInactiveLicenseStatus()
                ]
            ]
        ];

        $this->client->expects($this->once())->method('request')->willReturn(new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode($responseData, JSON_THROW_ON_ERROR)
        ));

        $result = $this->provider->suspend($suspendParams);

        $this->assertSame('License already suspended', $result->getMessage());
    }

    /**
     * Get a status that maps an inactive license.
     */
    private function getInactiveLicenseStatus(): int
    {
        return array_rand([2, 4]);
    }
}
