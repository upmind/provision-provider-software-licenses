<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\Example\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * Example licensing API configuration.
 *
 * @property-read string $api_url Licensing API URL
 * @property-read string $api_token API auth token
 */
class Configuration extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'api_url' => ['required', 'url'],
            'api_token' => ['required', 'string'],
        ]);
    }
}
