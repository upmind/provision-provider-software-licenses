<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\Generic\ResponseHandlers;

use Upmind\ProvisionProviders\SoftwareLicenses\Exceptions\CannotParseResponse;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\Generic\Exceptions\ResponseMissingUsageData;

/**
 * Handler to parse a usage data from a PSR-7 response body.
 */
class UsageDataResponseHandler extends DefaultResponseHandler
{
    /**
     * @throws ResponseMissingUsageData If unsuccessful
     */
    public function assertResponseSuccess(): void
    {
        $this->getUnitsConsumed();
    }

    /**
     * Extract units consumed from the response.
     *
     * @throws ResponseMissingUsageData If usage data cannot be determined
     *
     * @param string $property Name of the property containing the units consumed
     *
     * @return integer Number of units consumed
     */
    public function getUnitsConsumed(string $property = 'units_consumed'): int
    {
        try {
            $unitsConsumed = $this->getData($property);

            if (!is_numeric($unitsConsumed)) {
                throw new CannotParseResponse(
                    sprintf('Unable to parse %s from service response', $property)
                );
            }

            return intval($unitsConsumed);
        } catch (CannotParseResponse $e) {
            throw (new ResponseMissingUsageData($e->getMessage(), 0, $e))
                ->withDebug([
                    'http_code' => $this->response->getStatusCode(),
                    'content_type' => $this->response->getHeaderLine('Content-Type'),
                    'body' => $this->getBody(),
                    $property => $unitsConsumed ?? null,
                ]);
        }
    }
}
