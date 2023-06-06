<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\Blesta\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * Blesta licensing API configuration.
 *
 * @property-read string $username Username
 * @property-read string $password Password
 * @property-read bool|null $debug Whether or not to log api calls
 */
class Configuration extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'debug' => ['nullable', 'boolean']
        ]);
    }
}
