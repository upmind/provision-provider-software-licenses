<?php

declare(strict_types=1);

namespace Unit\Providers\CPanel;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Str;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\CreateParams;
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

    /**
     * 1 => Active
     */
    private int $activeLicenseStatus = 1;

    /**
     * 2 => Expired
     * 4 => Suspended
     */
    private array $inactiveLicenseStatus = [2, 4];

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

    public function testCannotCreateIfPackageIdentifierIsNotSet(): void
    {
        $this->expectException(ProvisionFunctionError::class);

        $createParams = $this->createMock(CreateParams::class);

        $this->provider->create($createParams);
    }

    public function testCannotCreateMessageIfPackageIdentifierIsNotSet(): void
    {
        $this->expectExceptionMessage('Package identifier is required!');

        $createParams = $this->createMock(CreateParams::class);

        $this->provider->create($createParams);
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
    public function testSuspendInactiveLicense(): void
    {
        $suspendParams = $this->createMock(SuspendParams::class);

        $licenseKey = Str::random(6);

        $suspendParams->expects($this->exactly(2))
            ->method('__get')
            ->with('license_key')
            ->willReturn($licenseKey);

        $licenseResponseData = [
            'licenses' => [
                'L' . $licenseKey => [
                    'status' => $this->activeLicenseStatus
                ]
            ]
        ];

        $expireLicenseResponseData = [
            'licenseid' => $licenseKey
        ];

        $this->client->expects($this->exactly(2))->method('request')->willReturn(
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode($licenseResponseData, JSON_THROW_ON_ERROR)
            ),
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode($expireLicenseResponseData, JSON_THROW_ON_ERROR)
            ),
        );

        $result = $this->provider->suspend($suspendParams);

        $this->assertEquals('License suspended', $result->getMessage());
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
                    'status' => $this->inactiveLicenseStatus[array_rand($this->inactiveLicenseStatus)]
                ]
            ]
        ];

        $this->client->expects($this->once())->method('request')->willReturn(new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode($responseData, JSON_THROW_ON_ERROR)
        ));

        $result = $this->provider->suspend($suspendParams);

        $this->assertEquals('License already suspended', $result->getMessage());
    }
}
