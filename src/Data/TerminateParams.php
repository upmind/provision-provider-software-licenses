<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * @property-read mixed $license_key License key
 * @property-read string|int|null $customer_identifier Service customer identifier, if any
 */
class TerminateParams extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'license_key' => ['required'],
            'customer_identifier' => ['nullable'],
        ]);
    }
}
