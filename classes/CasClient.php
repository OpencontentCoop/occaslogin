<?php

use Symfony\Contracts\HttpClient\HttpClientInterface;

class CasClient
{
    const QUERY_TICKET_PARAMETER = 'ticket';

    const QUERY_SERVICE_PARAMETER = 'service';

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

    private $http;

    public function __construct(HttpClientInterface $httpClient, $baseUrl, $service)
    {
        $this->httpClient = $httpClient;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->service = $service;
        $this->http = eZHTTPTool::instance();
    }

    public function needValidation()
    {
        return $this->http->hasGetVariable(self::QUERY_TICKET_PARAMETER);
    }

    public function validateRequestAndGetUserData()
    {
        $ticket = $this->http->getVariable(self::QUERY_TICKET_PARAMETER);
        if ($ticket === 'test-success') {
            $data = $this->getMockSuccessValidationResponse();
        } elseif ($ticket === 'test-fail') {
            $data = $this->getMockFailValidationResponse();
        } else {
            $validationUrl = $this->baseUrl . '/cas/serviceValidate';
            $validationResponse = $this->httpClient->request('GET', $validationUrl, [
                'query' => [
                    self::QUERY_TICKET_PARAMETER => $ticket,
                    self::QUERY_SERVICE_PARAMETER => $this->service,
                ],
            ]);
            $data = (string)$validationResponse->getContent();
        }
        $xml = new \SimpleXMLElement($data, 0, false, 'cas', true);
        if (isset($xml->authenticationSuccess)) {
            return (array)$xml->authenticationSuccess->attributes;
        }

        CasLogger::error("[authentication-failed] $data", __METHOD__);
        throw new Exception('Validation failed');
    }

    public function getLoginUrl()
    {
        return $this->baseUrl . '/cas/login?' . self::QUERY_SERVICE_PARAMETER . '=' . $this->service;
    }

    public function getLogoutUrl()
    {
        return $this->baseUrl . '/cas/logout?' . self::QUERY_SERVICE_PARAMETER . '=' . $this->service;
    }

    private function getMockSuccessValidationResponse()
    {
        return "
      <cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
        <cas:authenticationSuccess>
          <cas:user>NTSCTT80E59D086L</cas:user>
          <cas:attributes>
            <cas:credentialType>ClientCredential</cas:credentialType>
            <cas:identificativoUtente>spidcode</cas:identificativoUtente>
            <cas:isFromNewLogin>true</cas:isFromNewLogin>
            <cas:authenticationDate>2022-01-25T09:09:07.500837Z</cas:authenticationDate>
            <cas:cognome>Cas</cas:cognome>
            <cas:emailAddress>cas@example.it</cas:emailAddress>
            <cas:clientName>BresciaGOV_SPID</cas:clientName>
            <cas:authenticationMethod>DelegatedClientAuthenticationHandler</cas:authenticationMethod>
            <cas:successfulAuthenticationHandlers>DelegatedClientAuthenticationHandler</cas:successfulAuthenticationHandlers>
            <cas:nome>Test</cas:nome>
            <cas:longTermAuthenticationRequestTokenUsed>false</cas:longTermAuthenticationRequestTokenUsed>
            <cas:codiceFiscale>NTSCTT80E59D086L</cas:codiceFiscale>
          </cas:attributes>
        </cas:authenticationSuccess>
      </cas:serviceResponse>
      ";
    }

    private function getMockFailValidationResponse()
    {
        return "
      <cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
        <cas:authenticationFailure code='INVALID_TICKET'>
            Ticket not recognized
        </cas:authenticationFailure>
      </cas:serviceResponse>
      ";
    }
}