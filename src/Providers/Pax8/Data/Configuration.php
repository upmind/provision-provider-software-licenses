<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\Pax8\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * Pax8 licensing API configuration.
 *
 * @property-read string $client_id Client ID
 * @property-read string $client_secret Client Secret
 */
class Configuration extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'client_id' => ['required', 'string'],
            'client_secret' => ['required', 'string'],
        ]);
    }
}
