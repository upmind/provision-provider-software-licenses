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
        if (!$productId) {
            $productId = $this->getProductById($params->package_identifier);
        }

        if (!$productId) {
            $this->errorResult('Cannot find package!');
        }

        try {
            $companyId = null;

            if (!empty($params->customer_name)) {
                $companyId = $this->getCompanyByName($params->customer_name);
            }

            if (!$companyId && !empty($params->company_name)) {
                $company = $this->getCompanyById($params->company_name);
                if ($company) {
                    $companyId = $params->company_name;
                }
            }

            if (!$companyId) {
                if (!(isset($params->extra['address']))) {
                    $this->errorResult('Extra.address is required!');
                }

                $phone = $params->extra['phone'] ?? '00000000';

                $address = $params->extra['address'];

                $companyId = $this->createCompany($params->customer_name, $params->customer_email, $address, $params->customer_identifier, $phone);
            }

            $lineItem = [
                'productId' => $productId,
                'billingTerm' => 'Monthly',
                'lineItemNumber' => 1,
                'quantity' => 1,
            ];

            if (isset($params->billing_cycle_months) && $params->billing_cycle_months > 1) {
                switch ($params->billing_cycle_months) {
                    case 12:
                        $lineItem['billingTerm'] = 'Annual';
                        break;
                    case 24:
                        $lineItem['billingTerm'] = '2-Year';
                        break;
                    case 36:
                        $lineItem['billingTerm'] = '3-Year';
                        break;
                    default:
                        $lineItem['billingTerm'] = 'Monthly';
                        break;
                }
            }

            $dependency = $this->getProductDependencies($productId, $lineItem['billingTerm']);
            if ($dependency) {
                $lineItem['commitmentTermId'] = $dependency['id'];
                $lineItem['billingTerm'] = $dependency['term'];
            }

            if ($lineItem['billingTerm'] == '1-Year') {
                $lineItem['billingTerm'] = 'Annual';
            }

            $lineItem['provisioningDetails'] = $this->buildProvisioningDetails($params);;

            $body = [
                'companyId' => $companyId,
                'orderedByUserEmail' => $params->customer_email,
                'orderedBy' => 'Customer',
                'lineItems' => [
                    $lineItem,
                ],
            ];

            $licenseId = null;
            $response = $this->makeRequest('orders', ['isMock' => 'false'], $body);
            foreach ($response['lineItems'] as $lineItem) {
                if ($lineItem['productId'] == $productId) {
                    $licenseId = $lineItem['subscriptionId'];
                }
            }

            return CreateResult::create(['license_key' => (string)$licenseId])
                ->setMessage('License created');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }


    /**
     * @param CreateParams $params
     * @return array
     */
    function buildProvisioningDetails(CreateParams $params): array
    {
        $sharedDetails = $this->getSharedMicrosoftDetails();

        if (isset($params->customer_identifier)) {
            $details = $this->getExistingCustomerDetails($params->customer_identifier);
        } else {
            @[$firstName, $lastName] = explode(' ', $params->customer_name, 2);
            $details = $this->getNewCustomerDetails($firstName, $lastName, $params->customer_email ?? '');
        }

        $details = array_merge($details, $sharedDetails);

        return $this->formatProvisioningDetails($details);
    }

    /**
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @return string[]
     */
    function getNewCustomerDetails(
        string $firstName,
        string $lastName,
        string $email
    ): array
    {
        return [
            'msCustExists' => 'No, the customer does not have a Microsoft account',

            'mca2020FirstName' => $firstName,
            'mca2020LastName' => $lastName,
            'mca2020Email' => $email,

            'msftContactFirstName' => $firstName,
            'msftContactLastName' => $lastName,
            'msftContactEmail' => $email,
        ];
    }

    /**
     * @param string $tenantId
     * @return string[]
     */
    function getExistingCustomerDetails(string $tenantId): array
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
    function getSharedMicrosoftDetails(): array
    {
        return [
            'microsoftCancelPolicyAcknowledgement' =>
                'I understand, and acknowledge that I will have a 7 calendar day window to cancel my subscription, or make quantity decrements before I am no longer able to make these changes. Once a subscription is locked, I will be required fulfill my elected commitment term of my subscription.',
            'microsoftTrialConversion' =>
                'I understand and acknowledge that at the conclusion of my Microsoft trial license period (30 days), my 25 trial subscriptions will automatically convert to 25 paid subscriptions.'
        ];
    }


    /**
     * @param array $details
     * @return array
     */
    function formatProvisioningDetails(array $details): array
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
     * @param RenewParams $params
     * @return RenewResult
     * @throws Throwable
     */
    public function renew(RenewParams $params): RenewResult
    {

        $this->unsuspendSubscription($params->license_key);
        return RenewResult::create()
            ->setLicenseKey($params->license_key)
            ->setPackageIdentifier($params->package_identifier)
            ->setMessage('Renewal not required for Pax8 licenses');
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
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws ProvisionFunctionError
     * @throws \Throwable
     */
    public function changePackage(ChangePackageParams $params): ChangePackageResult
    {
        $this->errorResult('Operation not supported');
    }

    /**
     * @inheritDoc
     *
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
            return EmptyResult::create()->setMessage('Provisioning task in progress');
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
        if ($this->isLicenseInProgress($params->license_key)) {
            return EmptyResult::create()->setMessage('Provisioning task in progress');
        }

        if ($this->isLicenseActive($params->license_key)) {
            return EmptyResult::create()->setMessage('License already active');
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
            return EmptyResult::create()->setMessage('Provisioning task in progress');
        }

        if ($this->isLicenseExpired($params->license_key)) {
            return EmptyResult::create()->setMessage('License already expired');
        }

        return $this->cancelSubscription($params->license_key);
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
     * @throws ProvisionFunctionError
     * @throws RuntimeException
     */
    private function getAuthToken(): string
    {
        $body = [
            'client_id' => $this->configuration->clientId,
            'client_secret' => $this->configuration->clientSecret,
            'audience' => 'https://api.pax8.com',
            'grant_type' => 'client_credentials',
        ];

        $response = $this->makeRequest('token', null, $body);

        return $response['access_token'];
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
        $result = $response->getBody()->getContents();

        $response->getBody()->close();

        if ($result === '') {
            return null;
        }

        return $this->parseResponseData($result);
    }

    /**
     * @throws ProvisionFunctionError
     */
    private function parseResponseData(string $result): ?array
    {
        $parsedResult = json_decode($result, true);

        if (!$parsedResult && $parsedResult != []) {
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
        $date = new DateTime('now');

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
     * @param string $customer
     * @return string|null
     * @throws GuzzleException
     */
    private function getCompanyByName(string $customer): ?string
    {
        $query = [
            'size' => 200,
        ];

        for ($page = 0; ; $page++) {
            $query['page'] = $page;

            $response = $this->makeRequest('companies', $query, null, 'GET');
            if (!isset($response['content'])) {
                return null;
            }

            foreach ($response['content'] as $company) {
                if ($company['name'] === $customer) {
                    return $company['id'];
                }
            }
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
        return (array)$response;
    }

    /**
     * @param string $value
     * @return string|null
     * @throws GuzzleException
     */
    private function getProductId(string $value): ?string
    {
        $query = [
            'search' => $value,
            'vendorName' => 'Microsoft',
            'size' => 1,
        ];

        $response = $this->makeRequest('products', $query, null, 'GET');
        if (!isset($response['content'])) {
            return null;
        }

        return $response['content'][0]['id'];
    }


    /**
     * @param string $package_identifier
     * @return string|null
     * @throws GuzzleException
     */
    private function getProductById(string $package_identifier): ?string
    {
        return $this->makeRequest("products/{$package_identifier}", null, null, 'GET')['id'];
    }

    /**
     * @param string $customer_name
     * @param string $customer_email
     * @param array $address
     * @param string $website
     * @param string $phone
     * @return string
     * @throws GuzzleException
     */
    private function createCompany(string $customer_name, string $customer_email, array $address, string $website, string $phone): string
    {
        $body = [
            'address' => $address,
            'billOnBehalfOfEnabled' => false,
            'selfServiceAllowed' => false,
            'orderApprovalRequired' => false,
            'name' => $customer_name,
            'phone' => $phone,
            'website' => $website,
        ];

        $response = $this->makeRequest('companies', null, $body);
        $companyId = $response['id'];

        $this->createContacts($customer_name, $customer_email, $companyId, $phone);

        return (string)$companyId;
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
            'lastName' => $lastName != "" ? $lastName : $firstName,
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
        if (!$response['commitmentDependencies']) {
            return null;
        }

        foreach ($response['commitmentDependencies'] as $dependency) {
            if ($dependency['term'] == $billingTerm) {
                return $dependency;
            }
        }

        return $response['commitmentDependencies'][0];
    }
}
