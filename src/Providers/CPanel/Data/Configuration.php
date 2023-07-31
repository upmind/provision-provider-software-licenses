<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\CPanel\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * CPanel licensing API configuration.
 *
 * @property-read string $username Username
 * @property-read string $password Password
 * @property-read integer|null $group_id Group ID
 * @property-read bool|null $debug Whether or not to log api calls
 */
class Configuration extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'group_id' => ['nullable', 'integer'],
            'debug' => ['nullable', 'boolean']
        ]);
    }
}
