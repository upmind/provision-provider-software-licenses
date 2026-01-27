<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\Pax8\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * Pax8 licensing API configuration.
 *
 * @property-read string $clientId Client ID
 * @property-read string $clientSecret Client Secret
 */
class Configuration extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'clientId' => ['required', 'string'],
            'clientSecret' => ['required', 'string'],
        ]);
    }
}
