<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\WHMCS\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * WHMCS licensing API configuration.
 *
 * @property-read string $email Email
 * @property-read string $api_key API key
 */
class Configuration extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'email' => ['required', 'string'],
            'api_key' => ['required', 'string'],
        ]);
    }
}
