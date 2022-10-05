<?php

interface CasClientInterface
{
    public function needValidation();

    public function validateRequestAndGetUserData();

    public function getLoginUrl();

    public function getLogoutUrl();
}