<?php

/** @var eZModule $Module */
$Module = $Params['Module'];

try {
    if (!eZHTTPTool::instance()->hasSessionVariable(CasUserHandler::SESSION_VAR)) {
        throw new Exception('Cas user session not found');
    }
    eZHTTPTool::instance()->removeSessionVariable(CasUserHandler::SESSION_VAR);
    $Module->RedirectURI = CasClientFactory::instance()->makeClient()->getLogoutUrl();
    $Module->setExitStatus(eZModule::STATUS_REDIRECT);
    return;
} catch (Exception $e) {
    CasLogger::error("[logout] " . $e->getMessage(), __FILE__);
    $Module->redirectTo('/');
    return;
}