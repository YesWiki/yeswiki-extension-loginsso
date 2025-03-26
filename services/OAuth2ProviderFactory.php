<?php

namespace YesWiki\LoginSso\Service;

use YesWiki\Wiki;

class OAuth2ProviderFactory
{
    protected $wiki;

    public function __construct(Wiki $wiki)
    {
        $this->wiki = $wiki;
    }

    public function createProvider(int $providerId): \League\OAuth2\Client\Provider\GenericProvider
    {
        $confEntry = $this->wiki->config['sso_config']['providers'][$providerId]; // TODO: multiple providers

        $redirectUri = $this->wiki->getBaseUrl() . CallbackPathProvider::CALLBACK_PATH;
        if($confEntry['auth_options']['useProxyForCallback'] ?? false) {
            $redirectUri = $this->wiki->getBaseUrl() . '/tools/loginsso/proxy_callback.php';
        }
        if($confEntry['auth_options']['addFinalEqual'] ?? true) {
            $redirectUri .= '=';
        }

        return new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId' => $confEntry['auth_options']['clientId'],    // The client ID assigned to you by the provider
            'clientSecret' => $confEntry['auth_options']['clientSecret'],   // The client password assigned to you by the provider
            'redirectUri' => $redirectUri,
            'urlAuthorize' => $confEntry['auth_options']['urlAuthorize'],
            'urlAccessToken' => $confEntry['auth_options']['urlAccessToken'],
            'urlResourceOwnerDetails' => $confEntry['auth_options']['urlResourceOwnerDetails'],
            'scopes' => $confEntry['auth_options']['scopes'] ?? [ 'openid' ],
            'scopeSeparator' => $confEntry['auth_options']['scopeSeparator'] ?? ' '
        ]);
    }
}
