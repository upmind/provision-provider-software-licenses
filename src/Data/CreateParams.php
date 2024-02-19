<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * @property-read string $customer_name Name of the customer
 * @property-read string $customer_email Email address of the customer
 * @property-read string|null $company_name Company name of the customer
 * @property-read string|int|null $customer_identifier Service customer identifier, if already created
 * @property-read string|null $service_identifier Secondary service identifier to use, if known up-front
 * @property-read string|null $package_identifier Service package identifier, if any
 * @property-read string|null $ip IP address
 * @property-read mixed[]|null $extra Any extra data to pass to the service endpoint
 */
class CreateParams extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'customer_name' => ['required', 'string'],
            'customer_email' => ['required', 'email'],
            'company_name' => ['nullable', 'string'],
            'customer_identifier' => ['nullable'],
            'service_identifier' => ['nullable', 'string'],
            'package_identifier' => ['nullable', 'string'],
            'ip' => ['nullable', 'ip'],
            'extra' => ['nullable', 'array'],
        ]);
    }
}
