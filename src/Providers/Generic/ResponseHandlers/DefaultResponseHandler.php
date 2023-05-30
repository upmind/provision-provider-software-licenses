<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\Generic\ResponseHandlers;

use Upmind\ProvisionProviders\SoftwareLicenses\Exceptions\CannotParseResponse;
use Upmind\ProvisionProviders\SoftwareLicenses\Exceptions\OperationFailed;
use Upmind\ProvisionProviders\SoftwareLicenses\ResponseHandlers\AbstractHandler;

/**
 * Handler to determine success/failure from a PSR-7 response body.
 */
class DefaultResponseHandler extends AbstractHandler
{
    public function assertResponseSuccess(): void
    {
        try {
            if (is_null($this->getData())) {
                return; // empty response, call it success because we got a 2xx response
            }

            $possibleKeys = [
                'success',
                'result',
            ];

            $successValues = [
                'success',
                'ok',
                true,
                'true',
                1,
                '1'
            ];

            foreach ($possibleKeys as &$key) {
                $value = $this->getData($key);

                if (is_null($value)) {
                    continue;
                }

                if (in_array($value, $successValues, true)) {
                    return; // this looks like a success
                }

                throw new CannotParseResponse('Operation failed');
            }
            unset($key);
            unset($value);

            throw new CannotParseResponse('Unable to parse result from service response');
        } catch (CannotParseResponse $e) {
            throw (new OperationFailed($e->getMessage(), 0, $e))
                ->withDebug([
                    'http_code' => $this->response->getStatusCode(),
                    'content_type' => $this->response->getHeaderLine('Content-Type'),
                    'body' => $this->getBody(),
                    'result_key' => $key ?? null,
                    'result_value' => $value ?? null,
                ]);
        }
    }
}
