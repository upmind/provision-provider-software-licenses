<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\Pax8;

use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use RuntimeException;
use Throwable;
use Upmind\ProvisionBase\Provider\Contract\ProviderInterface;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionProviders\SoftwareLicenses\Category;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\ChangePackageParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\ChangePackageResult;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\CreateParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\CreateResult;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\CustomerAddressParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\EmptyResult;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\GetUsageParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\GetUsageResult;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\ReissueParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\ReissueResult;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\RenewParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\RenewResult;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\SuspendParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\TerminateParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\UnsuspendParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\Pax8\Data\Configuration;

/**
 * Pax8 provider.
 */
class Provider extends Category implements ProviderInterface
{
    protected Configuration $configuration;
    protected ?Client $client = null;
    protected ?string $token = null;
    private ?string $customerDomainPrefix = null;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @inheritDoc
     */
    public static function aboutProvider(): AboutData
    {
        return AboutData::create()
            ->setName('Pax8 M365')
            ->setLogoUrl('https://api.upmind.io/images/logos/provision/pax8-logo.png')
            ->setDescription('Resell, provision and manage Pax8 M365 licenses');
    }

    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws ProvisionFunctionError
     * @throws \Throwable
     */
    public function getUsageData(GetUsageParams $params): GetUsageResult
    {
        return GetUsageResult::create()
            ->setUsageData($this->getSubscription($params->license_key));
    }

    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws ProvisionFunctionError
     * @throws \Throwable
     */
    public function create(CreateParams $params): CreateResult
    {
        if (!isset($params->package_identifier)) {
            $this->errorResult('Package identifier is required!');
        }

        $productId = $this->getProductId($params->package_identifier);

        // If product not found by name or SKU, try by ID
        if (!$productId) {
            try {
                $productId = $this->getProductById($params->package_identifier);
            } catch (Throwable $e) {
                $this->handleException($e);
            }
        }

        if (!$productId) {
            $this->errorResult('Cannot find package!');
        }

        switch ($params->billing_cycle_months) {
            case null:
            case 1:
                $billingTerm = 'Monthly';
                break;
            case 12:
                $billingTerm = 'Annual';
                break;
            case 24:
                $billingTerm = '2-Year';
                break;
            case 36:
                $billingTerm = '3-Year';
                break;
            default:
                $this->errorResult('Invalid billing cycle months!', [], [
                    'billing_cycle_months' => $params->billing_cycle_months,
                    'allowed_billing_cycle_months' => [null, 1, 12, 24, 36]
                ]);
        }

        // First validate that either we have a customer identifier, or a customer address.
        if (empty($params->customer_identifier) && empty($params->customer_address)) {
            $this->errorResult(
                'Either Customer identifier or Customer address is required'
            );
        }

        // If customer identifier is provided, try to get the company
        if (!empty($params->customer_identifier)) {
            try {
                $company = $this->getCompanyById($params->customer_identifier);

                $companyId = (string) $company['id'];

                // Now build provisioning details for existing customer.
                $provisioningDetails = $this->buildProvisioningDetails($params, null, true);
            } catch (Throwable $t) {
                $this->handleException($t);
            }
        } else {
            // If not an existing company, create it.

            // Use phone library to handle in local format.
            $customerPhone = $params->customer_phone !== null ? phone($params->customer_phone) : '00000000';

            try {
                $companyId = $this->createCompany(
                    $params->customer_name,
                    $params->customer_email,
                    $params->customer_address,
                    $params->service_identifier ?? $this->getCustomerDomainPrefix(),
                    is_string($customerPhone) ? $customerPhone : $customerPhone->formatNational()
                );

                // Now build provisioning details for new customer.
                $provisioningDetails = $this->buildProvisioningDetails($params, $this->getCustomerDomainPrefix());
            } catch (Throwable $e) {
                $this->handleException($e);
            }
        }

        // Now create the order to fetch the license.
        $lineItem = [
            'productId' => $productId,
            'billingTerm' => $billingTerm,
            'lineItemNumber' => 1,
            'quantity' => 1,
            'provisioningDetails' => $provisioningDetails,
        ];

        try {
            $dependency = $this->getProductDependencies($productId, $lineItem['billingTerm']);

            if ($dependency) {
                $lineItem['commitmentTermId'] = $dependency['id'];
                $lineItem['billingTerm'] = $dependency['term'];
            }

            if ($lineItem['billingTerm'] === '1-Year') {
                $lineItem['billingTerm'] = 'Annual';
            }

            $body = [
                'companyId' => $companyId,
                'orderedByUserEmail' => $params->customer_email,
                'orderedBy' => 'Customer',
                'lineItems' => [
                    $lineItem,
                ],
            ];

            $response = $this->makeRequest('orders', ['isMock' => 'false'], $body);

            foreach ($response['lineItems'] as $lineItem) {
                if (isset($lineItem['productId']) && (string) $lineItem['productId'] === $productId) {
                    $licenseId = $lineItem['subscriptionId'] ?? null;
                }

                // Break early if we have found the license ID
                if (isset($licenseId)) {
                    break;
                }
            }

            if (!isset($licenseId)) {
                $this->errorResult('Unable to create license', [], [
                    'package_identifier' => $productId,
                    'customer_identifier' => $companyId,
                ]);
            }

            return CreateResult::create([
                'license_key' => (string) $licenseId,
                'package_identifier' => $productId,
                'customer_identifier' => $companyId,
            ])->setMessage('License created');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * @param RenewParams $params
     * @return RenewResult
     * @throws Throwable
     */
    public function renew(RenewParams $params): RenewResult
    {
        try {
            $this->unsuspend(UnsuspendParams::create([
                'license_key' => $params->license_key,
                'customer_identifier' => $params->customer_identifier,
            ]));
        } catch (ProvisionFunctionError $e) {
            $this->errorResult('License cannot be unsuspended for renewal', [], [], $e);
        }

        return RenewResult::create()
            ->setLicenseKey($params->license_key)
            ->setPackageIdentifier($params->package_identifier)
            ->setMessage('License is active, renewal not required');
    }

    /**
     * @throws ProvisionFunctionError
     */
    public function changePackage(ChangePackageParams $params): ChangePackageResult
    {
        $this->errorResult('Operation not supported');
    }

    /**
     * @throws ProvisionFunctionError
     */
    public function reissue(ReissueParams $params): ReissueResult
    {
        $this->errorResult('Operation not supported');
    }

    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws ProvisionFunctionError
     * @throws \Throwable
     */
    public function suspend(SuspendParams $params): EmptyResult
    {
        if ($this->isLicenseInProgress($params->license_key)) {
            $this->errorResult('License cannot be suspended while a Provisioning task is in progress');
        }

        if ($this->isLicenseExpired($params->license_key)) {
            return EmptyResult::create()->setMessage('License already suspended');
        }

        // All we can do is expire the license
        return $this->cancelSubscription($params->license_key, 'License suspended');
    }

    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws ProvisionFunctionError
     * @throws \Throwable
     */
    public function unsuspend(UnsuspendParams $params): EmptyResult
    {
        // First check if license is already active, no need for further action.
        if ($this->isLicenseActive($params->license_key)) {
            return EmptyResult::create()->setMessage('License already active');
        }

        if ($this->isLicenseInProgress($params->license_key)) {
            $this->errorResult('License cannot be unsuspended while a Provisioning task is in progress');
        }

        return $this->unsuspendSubscription($params->license_key);
    }

    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws ProvisionFunctionError
     * @throws \Throwable
     */
    public function terminate(TerminateParams $params): EmptyResult
    {
        if ($this->isLicenseInProgress($params->license_key)) {
            $this->errorResult('License cannot be terminated while a Provisioning task is in progress');
        }

        if ($this->isLicenseExpired($params->license_key)) {
            return EmptyResult::create()->setMessage('License already expired');
        }

        return $this->cancelSubscription($params->license_key);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws ProvisionFunctionError
     */
    public function makeRequest(string $command, ?array $params = null, ?array $body = null, ?string $method = 'POST'): ?array
    {
        $requestParams = [];

        if ($params) {
            $requestParams['query'] = $params;
        }

        if ($body) {
            $requestParams['json'] = $body;
        }

        if ($command !== 'token') {
            if (!$this->token) {
                $this->token = $this->getAuthToken();
            }

            $requestParams['headers'] = [
                'authorization' => 'Bearer ' . $this->token,
            ];
        }

        $response = $this->client()->request($method, "/v1/{$command}", $requestParams);
        $result = $response->getBody()->__toString();

        $response->getBody()->close();

        if ($result === '') {
            return null;
        }

        return $this->parseResponseData($result);
    }

    /**
     * @return no-return
     * @throws \Throwable
     *
     */
    protected function handleException(Throwable $e): void
    {
        if (($e instanceof ClientException || $e instanceof ServerException) && $e->hasResponse()) {
            /** @var \Psr\Http\Message\ResponseInterface $response */
            $response = $e->getResponse();

            $responseBody = $response->getBody()->__toString();
            $responseData = json_decode($responseBody, true);

            $errorMessage = $responseData['message'] ?? null;

            $this->errorResult(
                sprintf('Provider API Error: %s', $errorMessage),
                ['response_data' => $responseData],
                [],
                $e
            );
        }

        throw $e;
    }

    protected function client(): Client
    {
        if (isset($this->client)) {
            return $this->client;
        }

        $client = new Client([
            'base_uri' => 'https://api.pax8.com',
            'connect_timeout' => 10,
            'headers' => [
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ],
            'timeout' => 60,
            'handler' => $this->getGuzzleHandlerStack(),
        ]);

        return $this->client = $client;
    }

    /**
     * Get license data by key.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws ProvisionFunctionError
     * @throws \Throwable
     */
    protected function getSubscription(string $license_key): ?array
    {
        try {
            $response = $this->makeRequest("subscriptions/{$license_key}", null, null, 'GET');
            return (array)$response;

        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * @throws ProvisionFunctionError
     * @throws RuntimeException
     */
    private function getAuthToken(): string
    {
        $body = [
            'client_id' => $this->configuration->client_id,
            'client_secret' => $this->configuration->client_secret,
            'audience' => 'https://api.pax8.com',
            'grant_type' => 'client_credentials',
        ];

        $response = $this->makeRequest('token', null, $body);

        return $response['access_token'];
    }

    private function buildProvisioningDetails(
        CreateParams $params,
        ?string $domainPrefix,
        bool $existingCustomer = false
    ): array {
        $sharedDetails = $this->getSharedMicrosoftDetails();

        if ($existingCustomer) {
            $details = $this->getExistingCustomerDetails($params->customer_identifier);
        } else {
            @[$firstName, $lastName] = explode(' ', $params->customer_name, 2);
            $details = $this->getNewCustomerDetails(
                $firstName,
                $lastName,
                $params->customer_email,
                $domainPrefix ?? $this->getCustomerDomainPrefix(),
            );
        }

        $details = array_merge($details, $sharedDetails);

        return $this->formatProvisioningDetails($details);
    }

    /**
     * @return string[]
     */
    private function getNewCustomerDetails(string $firstName, string $lastName, string $email, string $domain): array
    {
        return [
            'msCustExists' => 'No, the customer does not have a Microsoft account',
            'mca2020FirstName' => $firstName,
            'mca2020LastName' => $lastName,
            'mca2020Email' => $email,
            'msftContactFirstName' => $firstName,
            'msftContactLastName' => $lastName,
            'msftContactEmail' => $email,
            'msDomain' => $domain,
        ];
    }

    /**
     * @param string $tenantId
     * @return string[]
     */
    private function getExistingCustomerDetails(string $tenantId): array
    {
        return [
            'msCustExists' => 'Yes, the customer has and can log into their Microsoft account',
            'msTenantId' => $tenantId,
            'mca2020FirstName' => '',
            'mca2020LastName' => '',
            'mca2020Email' => '',
            'msftContactFirstName' => '',
            'msftContactLastName' => '',
            'msftContactEmail' => '',
        ];
    }

    /**
     * @return string[]
     */
    private function getSharedMicrosoftDetails(): array
    {
        return [
            'microsoftCancelPolicyAcknowledgement' =>
                'I understand, and acknowledge that I will have a 7 calendar day window to cancel my subscription, or make quantity decrements before I am no longer able to make these changes. Once a subscription is locked, I will be required fulfill my elected commitment term of my subscription.',
            'microsoftTrialConversion' =>
                'I understand and acknowledge that at the conclusion of my Microsoft trial license period (30 days), my 25 trial subscriptions will automatically convert to 25 paid subscriptions.'
        ];
    }

    private function formatProvisioningDetails(array $details): array
    {
        return array_map(
            fn($key, $value) => [
                'key' => $key,
                'values' => $value !== '' ? [$value] : [],
            ],
            array_keys($details),
            $details
        );
    }

    /**
     * @throws ProvisionFunctionError
     */
    private function parseResponseData(string $result): ?array
    {
        $parsedResult = json_decode($result, true);

        if (!$parsedResult && $parsedResult !== []) {
            throw ProvisionFunctionError::create('Unknown Provider API Error')
                ->withData([
                    'response' => $result,
                ]);
        }

        return $parsedResult;
    }


    /**
     * Is a license active?
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws ProvisionFunctionError
     * @throws \Throwable
     */
    private function isLicenseActive(string $licenseKey): bool
    {
        return in_array($this->getLicenseStatus($licenseKey), ['Active', 'Activated', 'PendingActivation']);
    }

    /**
     * Is a license expired?
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws ProvisionFunctionError
     * @throws \Throwable
     */
    private function isLicenseExpired(string $licenseKey): bool
    {
        return in_array($this->getLicenseStatus($licenseKey), ['Cancelled', 'PendingCancel']);
    }

    /**
     * Is a license expired?
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws ProvisionFunctionError
     * @throws \Throwable
     */
    private function isLicenseInProgress(string $licenseKey): bool
    {
        return in_array($this->getLicenseStatus($licenseKey), ['PendingManual', 'PendingAutomated']);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws ProvisionFunctionError
     * @throws \Throwable
     */
    private function getLicenseStatus(string $licenseKey): string
    {
        $licenseData = $this->getSubscription($licenseKey);
        $status = $licenseData['status'] ?? null;

        if ($status === null) {
            $this->errorResult('Unable to determine license status');
        }

        return (string)$status;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws ProvisionFunctionError
     * @throws \Throwable
     */
    private function cancelSubscription(string $subscriptionId, string $message = 'License cancelled'): EmptyResult
    {
        try {
            $this->makeRequest("subscriptions/{$subscriptionId}", null, null, 'DELETE');
            return EmptyResult::create()->setMessage($message);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }


    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws ProvisionFunctionError
     * @throws \Throwable
     */
    private function unsuspendSubscription(string $subscriptionId, string $message = 'License unsuspended'): EmptyResult
    {
        $date = new DateTime('now', 'UTC');

        $body = [
            'startDate' => $date->format('Y-m-d\TH:i:s.v')
        ];

        try {
            $this->makeRequest("subscriptions/{$subscriptionId}", null, $body, 'PUT');
            return EmptyResult::create()->setMessage($message);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * @param string $companyId
     * @return array
     * @throws GuzzleException
     */
    private function getCompanyById(string $companyId): array
    {
        $response = $this->makeRequest("companies/{$companyId}", null, null, 'GET');

        return (array) $response;
    }

    /**
     * Find a product by its name or SKU.
     *
     * @param string $value // Product name or SKU
     * @return string|null
     * @throws GuzzleException
     */
    private function getProductId(string $value): ?string
    {
        $query = [
            'search' => $value,
            'vendorName' => 'Microsoft',
            'size' => 10, // Sensible loop
        ];

        $response = $this->makeRequest('products', $query, null, 'GET');

        if (!isset($response['content'])) {
            return null;
        }

        $lowerCaseValue = mb_strtolower($value);

        // Try to match the product, first by name, then by SKU
        foreach ($response['content'] as $product) {
            if (isset($product['name']) && mb_strtolower($product['name']) === $lowerCaseValue) {
                return $product['id'];
            }

            if (isset($product['sku']) && mb_strtolower($product['sku']) === $lowerCaseValue) {
                return $product['id'];
            }
        }

        return null;
    }

    /**
     * @param string $package_identifier
     * @return string|null
     * @throws GuzzleException
     */
    private function getProductById(string $package_identifier): ?string
    {
        try {
            $product = $this->makeRequest("products/{$package_identifier}", null, null, 'GET');

            return $product['id'] ?? null;
        } catch (ClientException $e) {
            // Not found
            if ($e->getResponse()->getStatusCode() === 404) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * @throws GuzzleException
     * @throws ProvisionFunctionError
     */
    private function createCompany(
        string $customer_name,
        string $customer_email,
        CustomerAddressParams $address,
        string $website,
        string $phone
    ): string {
        $body = [
            'address' => [
                'street' => $address->address_1,
                'street2' => $address->address_2 ?? '',
                'city' => $address->city,
                'stateOrProvince' => $address->state,
                'postalCode' => $address->postcode,
                'country' => $address->country_code
            ],
            'billOnBehalfOfEnabled' => false,
            'selfServiceAllowed' => false,
            'orderApprovalRequired' => false,
            'name' => $customer_name,
            'phone' => $phone,
            'website' => $website,
        ];

        $response = $this->makeRequest('companies', null, $body);

        if (!isset($response['id'])) {
            $this->errorResult('Unable to create company');
        }

        $companyId = (string) $response['id'];

        $this->createContacts($customer_name, $customer_email, $companyId, $phone);

        return $companyId;
    }

    /**
     * @param string $customer_name
     * @param string $customer_email
     * @param string $companyId
     * @param string $phone
     * @return void
     * @throws GuzzleException
     */
    private function createContacts(string $customer_name, string $customer_email, string $companyId, string $phone): void
    {
        @[$firstName, $lastName] = explode(' ', $customer_name, 2);

        $contactBody = [
            'firstName' => $firstName,
            'lastName' => $lastName !== '' ? $lastName : $firstName,
            'email' => $customer_email,
            'phone' => $phone,
            'types' => [
                [
                    'type' => 'Admin',
                    'primary' => true,
                ],
                [
                    'type' => 'Billing',
                    'primary' => true,
                ],
                [
                    'type' => 'Technical',
                    'primary' => true,
                ]
            ]
        ];

        $this->makeRequest("companies/{$companyId}/contacts", null, $contactBody);
    }

    /**
     * @param string $productId
     * @param string $billingTerm
     * @return array|null
     * @throws GuzzleException
     */
    private function getProductDependencies(string $productId, string $billingTerm): ?array
    {
        $response = $this->makeRequest("products/{$productId}/dependencies", null, null, 'GET');

        if (!isset($response['commitmentDependencies'])) {
            return null;
        }

        foreach ($response['commitmentDependencies'] as $dependency) {
            if ((string) $dependency['term'] === $billingTerm) {
                return $dependency;
            }
        }

        return $response['commitmentDependencies'][0];
    }

    /**
     * Get a generated unique domain prefix for the customer.
     */
    private function getCustomerDomainPrefix(): string
    {
        if ($this->customerDomainPrefix === null) {
            $this->customerDomainPrefix = bin2hex(random_bytes(8));
        }

        return $this->customerDomainPrefix;
    }
}
