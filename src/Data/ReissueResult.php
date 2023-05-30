<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * @property-read mixed $license_key License key
 */
class ReissueResult extends ResultData
{
    public static function rules(): Rules
    {
        return new Rules([
            'license_key' => ['filled'],
        ]);
    }

    /**
     * Set the result license_key.
     */
    public function setLicenseKey(string $licenseKey): self
    {
        $this->setValue('license_key', $licenseKey);
        return $this;
    }
}
