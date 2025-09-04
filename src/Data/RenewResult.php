<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Data;

use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * @property-read mixed $license_key License key
 * @property-read string|null $package_identifier Service package identifier, if any
 */
class RenewResult extends ResultData
{
    public static function rules(): Rules
    {
        return new Rules([
            'license_key' => ['filled'],
            'package_identifier' => ['nullable', 'string'],
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

    /**
     * Set the result package identifier.
     */
    public function setPackageIdentifier(?string $packageIdentifier): self
    {
        $this->setValue('package_identifier', $packageIdentifier);
        return $this;
    }
}
