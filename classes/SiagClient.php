<?php

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SiagClient implements CasClientInterface
{
    const QUERY_TOKEN_PARAMETER = 'token';

    /**
     * @var HttpClientInterface
     */
    private $httpClient;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var string
     */
    private $service;

    private $httpTool;

    private $language;

    public function __construct(HttpClientInterface $httpClient, $baseUrl, $service)
    {
        $this->httpClient = $httpClient;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->service = $service;
        $this->httpTool = eZHTTPTool::instance();
        $locale = eZLocale::currentLocaleCode();
        $this->language = $locale === 'ita-IT' ? 'it' : '';
    }

    public function needValidation()
    {
        return $this->httpTool->hasGetVariable(self::QUERY_TOKEN_PARAMETER);
    }

    public function validateRequestAndGetUserData()
    {
        try {
            $token = $this->httpTool->getVariable(self::QUERY_TOKEN_PARAMETER);
            $profileUrl = $this->baseUrl . '/api/Auth/Profile/' . $token;
            $profileResponse = $this->httpClient->request('GET', $profileUrl, [
                'query' => [
                    'onlyAuth' => true,
                ],
            ]);
            $data = (string)$profileResponse->getContent();

            return json_decode($data, true);
        }catch (Exception $e){

            CasLogger::error("[authentication-failed] " . $e->getMessage(), __METHOD__);

            throw new Exception('Validation failed');
        }
    }

    public function getLoginUrl()
    {
        // TODO: Implement getLoginUrl() method.
        return "{$this->baseUrl}}/api/Auth/Login?targetUrl={$this->service}&acceptedAuthTypes=SPID%20CNS&onlyAuth=true&lang={$this->language}";
    }

    public function getLogoutUrl()
    {
        return "{$this->baseUrl}}/api/Auth/Logout?returnUrl={$this->service}";
    }
}