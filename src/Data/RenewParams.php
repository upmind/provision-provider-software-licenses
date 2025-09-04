<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * @property-read mixed $license_key License key
 * @property-read string|int|null $customer_identifier Service customer identifier, if any
 * @property-read string|null $package_identifier Service package identifier, if any
 * @property-read mixed[]|null $extra Any extra data to pass to the service endpoint
 */
class RenewParams extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'license_key' => ['required'],
            'customer_identifier' => ['nullable'],
            'package_identifier' => ['nullable', 'string'],
            'extra' => ['nullable', 'array'],
        ]);
    }
}
