<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\Generic\Exceptions;

use Upmind\ProvisionProviders\SoftwareLicenses\Exceptions\OperationFailed;

/**
 * Response was invalid and/or did not contain a license key.
 */
class ResponseMissingLicenseKey extends OperationFailed
{
    //
}
