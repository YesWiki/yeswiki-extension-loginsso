<?php

/**
 * This file is used to redirect the user to the correct YesWiki callback URL after a callback from the SSO provider.
 * This is used in the case SSO server is unable to properly build the callback URL to follow the YesWiki routing.
 */

require_once __DIR__ . '/services/CallbackPathProvider.php';
$newLocation = \YesWiki\LoginSso\Service\CallbackPathProvider::CALLBACK_PATH . '&' . $_SERVER['QUERY_STRING'];

header("Location: ".$newLocation);
die();