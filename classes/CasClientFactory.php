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
     * @return CasClient
     */
    public function makeClient()
    {
        if ($this->client === null) {
            $client = HttpClient::create();
            $settings = eZINI::instance('caslogin.ini')->group('Settings');
            if ($settings['EnableCasLogin'] !== 'enabled') {
                throw new InvalidArgumentException('Cas login is disabled by configuration');
            }
            $service = $settings['Service'];
            if (empty($service)){
                $service = 'https://' . eZINI::instance()->variable('SiteSettings', 'SiteURL');
            }
            $this->client = new CasClient(
                $client,
                $settings['BaseUrl'],
                $service
            );
        }

        return $this->client;
    }
}