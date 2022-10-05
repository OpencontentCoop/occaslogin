<?php

use Symfony\Component\HttpClient\HttpClient;

class CasClientFactory
{
    private static $instance;

    private $client;

    private function __construct()
    {
    }

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new CasClientFactory();
        }

        return self::$instance;
    }

    /**
     * @return CasClientInterface
     */
    public function makeClient()
    {
        if ($this->client === null) {
            $client = HttpClient::create();
            $settings = eZINI::instance('caslogin.ini')->group('Settings');
            if ($settings['EnableCasLogin'] !== 'enabled') {
                throw new InvalidArgumentException('Cas login is disabled by configuration');
            }
            $enableMock = isset($settings['EnableMockTokens']) && $settings['EnableMockTokens'] == 'enabled';
            $service = $settings['Service'];
            if (empty($service)){
                $service = 'https://' . eZINI::instance()->variable('SiteSettings', 'SiteURL');
            }
            $clientClassName = 'CasClient';
            if (isset($settings['ClientClassName'])){
                $clientClassName = $settings['ClientClassName'];
            }
            $this->client = new $clientClassName(
                $client,
                $settings['BaseUrl'],
                $service
            );
            if (!$this->client instanceof CasClientInterface){
                throw new InvalidArgumentException('Cas client must implements CasClientInterface');
            }
        }

        return $this->client;
    }
}