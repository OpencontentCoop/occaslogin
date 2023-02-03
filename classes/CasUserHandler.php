<?php

class CasUserHandler
{
    const SESSION_VAR = 'CASUserLoggedIn';

    protected $accountAttributeIdentifier;

    /**
     * @var eZContentClass
     */
    private $userClass;

    private $fiscalCode;

    private $email;

    private $login;

    private $attributes = [];

    public function __construct($userData)
    {
        $settings = eZINI::instance('caslogin.ini')->group('Settings');
        $clientClassName = 'CasClient';
        if (isset($settings['ClientClassName'])){
            $clientClassName = $settings['ClientClassName'];
        }

        $mapper = eZINI::instance('caslogin.ini')->group('AttributeMapper');
        if (eZINI::instance('caslogin.ini')->group('AttributeMapper_' . $clientClassName)){
            $mapper = eZINI::instance('caslogin.ini')->group('AttributeMapper_' . $clientClassName);
        }

        $this->fiscalCode = $userData[$mapper['FiscalCode']];
        $this->login = $userData[$mapper['UserLogin']];
        $this->email = $userData[$mapper['UserEmail']];

        foreach ($mapper['Attributes'] as $key => $map) {
            if (isset($userData[$map])) {
                $this->attributes[$key] = $userData[$map];
            }
        }

        foreach ($this->getUserClass()->dataMap() as $identifier => $classAttribute) {
            if ($classAttribute->attribute('data_type_string') == eZUserType::DATA_TYPE_STRING) {
                $this->accountAttributeIdentifier = $identifier;
            }
        }
    }

    private function getUserClass()
    {
        if ($this->userClass === null) {
            $ini = eZINI::instance();
            $this->userClass = eZContentClass::fetch($ini->variable("UserSettings", "UserClassID"));
            if (!$this->userClass instanceof eZContentClass) {
                throw new Exception('User class not found');
            }
        }

        return $this->userClass;
    }

    private function login()
    {
        $user = $this->getExistingUser();

        if ($user instanceof eZUser) {
            $userObject = $user->contentObject();
            if ($userObject instanceof eZContentObject) {
                CasLogger::log('debug', 'Auth user exist: update user data', __METHOD__);

                if (!$userObject->mainNodeID()) {
                    if (count($userObject->assignedNodes()) === 0) {
                        $nodeAssignment = eZNodeAssignment::create([
                            'contentobject_id' => $userObject->attribute('id'),
                            'contentobject_version' => $userObject->attribute('current_version'),
                            'parent_node' => (int)eZINI::instance()->variable("UserSettings", "DefaultUserPlacement"),
                            'is_main' => 1,
                        ]);
                        $nodeAssignment->store();
                        eZContentOperationCollection::publishNode(
                            $nodeAssignment->attribute('parent_node'),
                            $userObject->attribute('id'),
                            $userObject->attribute('current_version'),
                            false
                        );
                        CasLogger::log('debug', 'Force set main node to user', __METHOD__);
                    } else {
                        eZUserOperationCollection::publishUserContentObject($user->id());
                        eZUserOperationCollection::sendUserNotification($user->id());
                        CasLogger::log('debug', 'Force publish user and send notification', __METHOD__);
                    }
                    if ($user->attribute('email') !== $this->email) {
                        $userByEmail = eZUser::fetchByEmail($this->email);
                        if (!$userByEmail) {
                            $user->setAttribute('email', $this->email);
                            $user->store();
                            CasLogger::log('debug', 'Update user email', __METHOD__);
                        }
                    }
                }
                eZContentFunctions::updateAndPublishObject($user->contentObject(), ['attributes' => $this->attributes]);

                $this->loginUser($user);

                return $user;
            } else {
                eZUser::removeUser($user->id());
            }
        }

        CasLogger::log('debug', 'Auth user does not exist: create user', __METHOD__);

        if (empty($this->email)){
            $this->email = 'missing-mail-for-' . $this->login;
        }

        $hash = eZUser::passwordHashTypeName(eZUser::hashType());
        $this->attributes[$this->accountAttributeIdentifier] = $this->login . '|' . $this->email . '||' . $hash . '|1';
        $params = [];
        $params['creator_id'] = $this->getUserCreatorId();
        $params['class_identifier'] = $this->getUserClass()->attribute('identifier');
        $params['parent_node_id'] = $this->getUserParentNodeId();
        $params['attributes'] = $this->attributes;

        $contentObject = eZContentFunctions::createAndPublishObject($params);

        if ($contentObject instanceof eZContentObject) {
            $user = eZUser::fetch($contentObject->attribute('id'));
            if ($user instanceof eZUser) {
                $casUser = $this->getExistingUser();
                if ($casUser instanceof eZUser && $casUser->id() == $user->id()) {
                    $this->loginUser($user);
                    eZUserOperationCollection::sendUserNotification($user->id());
                    return $user;
                }
            }
        }

        throw new Exception("Error creating user", 1);
    }

    public function loginAndRedirect()
    {
        $redirectionURI = '/';
        if (empty($this->login)) {
            CasLogger::log('error', 'Missing login attribute in cas user data', __METHOD__);
        } else {
            $user = $this->login();
            $ini = eZINI::instance();
            if (is_object($user)) {
                // First, let's determine which attributes we should search redirection URI in.
                $userUriAttrName = '';
                $groupUriAttrName = '';
                if ($ini->hasVariable('UserSettings', 'LoginRedirectionUriAttribute')) {
                    $uriAttrNames = $ini->variable('UserSettings', 'LoginRedirectionUriAttribute');
                    if (is_array($uriAttrNames)) {
                        if (isset($uriAttrNames['user'])) {
                            $userUriAttrName = $uriAttrNames['user'];
                        }

                        if (isset($uriAttrNames['group'])) {
                            $groupUriAttrName = $uriAttrNames['group'];
                        }
                    }
                }

                $userObject = $user->attribute('contentobject');

                // 1. Check if redirection URI is specified for the user
                $userUriSpecified = false;
                if ($userUriAttrName) {
                    /** @var eZContentObjectAttribute[] $userDataMap */
                    $userDataMap = $userObject->attribute('data_map');
                    if (isset($userDataMap[$userUriAttrName])
                        && ($uriAttribute = $userDataMap[$userUriAttrName])
                        && ($uri = $uriAttribute->attribute('content'))) {
                        $redirectionURI = $uri;
                        $userUriSpecified = true;
                    }
                }

                // 2.Check if redirection URI is specified for at least one of the user's groups (preferring main parent group).
                if (!$userUriSpecified && $groupUriAttrName && $user->hasAttribute('groups')) {
                    $groups = $user->attribute('groups');

                    if (isset($groups) && is_array($groups)) {
                        $chosenGroupURI = '';
                        foreach ($groups as $groupID) {
                            $group = eZContentObject::fetch($groupID);
                            /** @var eZContentObjectAttribute[] $groupDataMap */
                            $groupDataMap = $group->attribute('data_map');
                            $isMainParent = ($group->attribute('main_node_id') == $userObject->attribute(
                                    'main_parent_node_id'
                                ));
                            if (isset($groupDataMap[$groupUriAttrName])) {
                                $uri = $groupDataMap[$groupUriAttrName]->attribute('content');
                                if ($uri) {
                                    if ($isMainParent) {
                                        $chosenGroupURI = $uri;
                                        break;
                                    } elseif (!$chosenGroupURI) {
                                        $chosenGroupURI = $uri;
                                    }
                                }
                            }
                        }

                        // if we've chose an URI from one of the user's groups.
                        if ($chosenGroupURI) {
                            $redirectionURI = $chosenGroupURI;
                        }
                    }
                }
            }
        }

        $http = eZHTTPTool::instance();
        eZURI::transformURI($redirectionURI);
        $http->redirect($redirectionURI);
        eZExecution::cleanExit();
    }

    private function getExistingUser()
    {
        $user = eZUser::fetchByName($this->login);
        if (!$user instanceof eZUser) {
            $user = $this->getUserByFiscalCode();
        }
        if (eZINI::instance('caslogin.ini')->variable('Settings', 'FindExistingUserByMail') === 'enabled') {
            if (!$user instanceof eZUser) {
                $user = eZUser::fetchByEmail($this->email);
            }
        }

        return $user;
    }

    private function getUserByFiscalCode()
    {
        $user = false;

        if (class_exists('OCCodiceFiscaleType')) {
            /** @var eZContentClassAttribute $attribute */
            foreach ($this->getUserClass()->dataMap() as $attribute) {
                if ($attribute->attribute('data_type_string') == OCCodiceFiscaleType::DATA_TYPE_STRING) {
                    $userObject = $this->fetchObjectByFiscalCode($attribute->attribute('id'));
                    if ($userObject instanceof eZContentObject) {
                        $user = eZUser::fetch($userObject->attribute('id'));
                    }
                }
            }
        }

        return $user;
    }

    private function fetchObjectByFiscalCode($contentClassAttributeID)
    {
        $query = "SELECT co.id
				FROM ezcontentobject co, ezcontentobject_attribute coa
				WHERE co.id = coa.contentobject_id
				AND co.current_version = coa.version								
				AND coa.contentclassattribute_id = " . intval($contentClassAttributeID) . "
				AND UPPER(coa.data_text) = '" . eZDB::instance()->escapeString(strtoupper($this->fiscalCode)) . "'";

        $result = eZDB::instance()->arrayQuery($query);
        if (isset($result[0]['id'])) {
            return eZContentObject::fetch((int)$result[0]['id']);
        }

        return false;
    }

    private function loginUser(eZUser $user)
    {
        $userID = $user->attribute('contentobject_id');

        // if audit is enabled logins should be logged
        eZAudit::writeAudit('user-login', ['User id' => $userID, 'User login' => $user->attribute('login')]);

        eZUser::updateLastVisit($userID, true);
        eZUser::setCurrentlyLoggedInUser($user, $userID);

        // Reset number of failed login attempts
        eZUser::setFailedLoginAttempts($userID, 0);

        eZHTTPTool::instance()->setSessionVariable(self::SESSION_VAR, true);
    }

    private function getUserCreatorId()
    {
        $ini = eZINI::instance();

        return $ini->variable("UserSettings", "UserCreatorID");
    }

    private function getUserParentNodeId()
    {
        $ini = eZINI::instance();
        $db = eZDB::instance();
        $defaultUserPlacement = (int)$ini->variable("UserSettings", "DefaultUserPlacement");
        $sql = "SELECT count(*) as count FROM ezcontentobject_tree WHERE node_id = $defaultUserPlacement";
        $rows = $db->arrayQuery($sql);
        $count = $rows[0]['count'];
        if ($count < 1) {
            $errMsg = ezpI18n::tr(
                'design/standard/user',
                'The node (%1) specified in [UserSettings].DefaultUserPlacement setting in site.ini does not exist!',
                null,
                [$defaultUserPlacement]
            );
            throw new Exception($errMsg, 1);
        }

        return $defaultUserPlacement;
    }
}
