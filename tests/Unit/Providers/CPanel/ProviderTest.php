<?php

declare(strict_types=1);

namespace Unit\Providers\CPanel;

use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\ReissueParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\CPanel\Data\Configuration;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\CPanel\Provider;

class ProviderTest extends TestCase
{
    private ReissueParams $reissueParams;
    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reissueParams = $this->createMock(ReissueParams::class);

        $configuration = $this->createMock(Configuration::class);
        $this->provider = new Provider($configuration);
    }

    public function testReissueIsNotSupported(): void
    {
        $this->expectException(ProvisionFunctionError::class);

        $this->provider->reissue($this->reissueParams);
    }

    public function testReissueIsNotSupportedMessage(): void
    {
        $this->expectExceptionMessage('Operation not supported');

        $this->provider->reissue($this->reissueParams);
    }
}
