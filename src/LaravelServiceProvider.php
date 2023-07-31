<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses;

use Upmind\ProvisionBase\Laravel\ProvisionServiceProvider;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\Generic\Provider as GenericProvider;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\Example\Provider as ExampleProvider;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\Blesta\Provider as BlestaProvider;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\WHMCS\Provider as WHMCSProvider;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\CPanel\Provider as CPanelProvider;

class LaravelServiceProvider extends ProvisionServiceProvider
{
    public function boot()
    {
        $this->bindCategory('software-licenses', Category::class);

        // $this->bindCategory('software-licenses', 'example', ExampleProvider::class);

        $this->bindProvider('software-licenses', 'generic', GenericProvider::class);
        $this->bindProvider('software-licenses', 'blesta', BlestaProvider::class);
        $this->bindProvider('software-licenses', 'whmcs', WHMCSProvider::class);
        $this->bindProvider('software-licenses', 'cpanel', CPanelProvider::class);
    }
}
