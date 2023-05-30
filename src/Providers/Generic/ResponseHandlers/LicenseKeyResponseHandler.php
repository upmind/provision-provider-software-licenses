<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\Generic\ResponseHandlers;

use Upmind\ProvisionProviders\SoftwareLicenses\Exceptions\CannotParseResponse;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\Generic\Exceptions\ResponseMissingLicenseKey;

/**
 * Handler to parse a license key from a PSR-7 response body.
 */
class LicenseKeyResponseHandler extends DefaultResponseHandler
{
    /**
     * @throws ResponseMissingLicenseKey If unsuccessful
     */
    public function assertResponseSuccess(): void
    {
        $this->getLicenseKey();
    }

    /**
     * Extract a license key from the response.
     *
     * @throws ResponseMissingLicenseKey If license key cannot be determined
     *
     * @param string $property Name of the property containing the license key
     *
     * @return string Valid license key
     */
    public function getLicenseKey(string $property = 'license_key'): string
    {
        try {
            $licenseKey = $this->getData($property);

            if (empty($licenseKey) || !is_scalar($licenseKey)) {
                throw new CannotParseResponse(
                    sprintf('Unable to parse valid %s from service response', $property)
                );
            }

            return $licenseKey;
        } catch (CannotParseResponse $e) {
            throw (new ResponseMissingLicenseKey($e->getMessage(), 0, $e))
                ->withDebug([
                    'http_code' => $this->response->getStatusCode(),
                    'content_type' => $this->response->getHeaderLine('Content-Type'),
                    'body' => $this->getBody(),
                    $property => $licenseKey ?? null,
                ]);
        }
    }

    public function getServiceIdentifier()
    {
        return $this->getData('service_identifier');
    }

    public function getPackageIdentifier()
    {
        return $this->getData('package_identifier');
    }
}
