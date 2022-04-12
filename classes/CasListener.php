<?php

class CasListener
{
    public static function onInput(eZURI $uri)
    {
        if ($uri->isEmpty()) {
            try {
                $client = CasClientFactory::instance()->makeClient();
                if ($client->needValidation()) {
                    eZDebug::writeDebug("Validate cas token in input listener", __METHOD__);
                    $userData = $client->validateRequestAndGetUserData();
                    $handler = new CasUserHandler($userData);
                    $handler->loginAndRedirect();
                }
            } catch (Exception $e) {
                CasLogger::error("[login] " . $e->getMessage(), __FILE__);
            }
        }
    }
}