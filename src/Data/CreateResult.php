<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Data;

use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * @property-read string $license_key License key
 * @property-read string|null $service_identifier Secondary service identifier, if any
 * @property-read string|null $package_identifier Service package identifier, if any
 * @property-read string|int|null $customer_identifier Service customer identifier, if any
 */
class CreateResult extends ResultData
{
    public static function rules(): Rules
    {
        return new Rules([
            'license_key' => ['required', 'string'],
            'service_identifier' => ['nullable', 'string'],
            'package_identifier' => ['nullable', 'string'],
            'customer_identifier' => ['nullable'],
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
     * Set the result secondary service identifier.
     */
    public function setServiceIdentifier(?string $serviceIdentifier): self
    {
        $this->setValue('service_identifier', $serviceIdentifier);
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

    /**
     * Set the result package identifier.
     *
     * @param string|int|null $customerIdentifier
     */
    public function setCustomerIdentifier($customerIdentifier): self
    {
        $this->setValue('customer_identifier', $customerIdentifier);
        return $this;
    }
}
