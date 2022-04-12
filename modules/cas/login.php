<?php

/** @var eZModule $Module */
$Module = $Params['Module'];

if (eZUser::currentUser()->isRegistered()) {
    $Module->redirectTo('/');
    return;
}

try {
    $client = CasClientFactory::instance()->makeClient();
    if ($client->needValidation()) {
        $userData = $client->validateRequestAndGetUserData();
        $handler = new CasUserHandler($userData);
        $handler->loginAndRedirect();
    } else {
        $redirectUrl = $client->getLoginUrl();
        $Module->RedirectURI = $redirectUrl;
        $Module->setExitStatus(eZModule::STATUS_REDIRECT);
        return;
    }
} catch (InvalidArgumentException $e) {
    CasLogger::error("[login] " . $e->getMessage(), __FILE__);
    return $Module->handleError(eZError::KERNEL_MODULE_NOT_FOUND, 'kernel');
} catch (Exception $e) {
    CasLogger::error("[login] " . $e->getMessage(), __FILE__);
    return $Module->handleError(eZError::KERNEL_NOT_FOUND, 'kernel');
}
