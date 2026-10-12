<?php

namespace WPGPTVibeVendor\OAuth2\TokenType;

use WPGPTVibeVendor\OAuth2\RequestInterface;
use WPGPTVibeVendor\OAuth2\ResponseInterface;

interface TokenTypeInterface
{
    /**
     * Token type identification string
     *
     * ex: "bearer" or "mac"
     */
    public function getTokenType();

    /**
     * Retrieves the token string from the request object
     */
    public function getAccessTokenParameter(RequestInterface $request, ResponseInterface $response);
}
