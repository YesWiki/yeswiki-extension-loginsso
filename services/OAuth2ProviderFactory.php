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

        return new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId'                => $confEntry['auth_options']['clientId'],    // The client ID assigned to you by the provider
            'clientSecret'            => $confEntry['auth_options']['clientSecret'],   // The client password assigned to you by the provider
            'redirectUri'             => $this->wiki->getBaseUrl() . '/?api/auth_sso/callback=', // Final '=' mandatory for lemonldap compatibility
            'urlAuthorize'            => $confEntry['auth_options']['urlAuthorize'],
            'urlAccessToken'          => $confEntry['auth_options']['urlAccessToken'],
            'urlResourceOwnerDetails' => $confEntry['auth_options']['urlResourceOwnerDetails'],
            'scopes' => ['openid']
        ]);
    }

}
