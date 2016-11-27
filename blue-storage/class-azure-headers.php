<?php
/**
 * Used for managing headers for making requests to Azure
 *
 * @package   Blue Storage
 * @author    Derek Held
 * @link      https://wordpress.org/plugins/blue-storage/
 */

namespace BlueStorage;

class AzureHeaders
{
    const AUTH_TYPE = 'SharedKey';

    protected $authArray = array('httpMethod' => '',
        'Content-Encoding' => '',
        'Content-Language' => '',
        'Content-Length' => '',
        'Content-MD5' => '',
        'Content-Type' => '',
        'Date' => '',
        'If-Modified-Since' => '',
        'If-Match' => '',
        'If-None-Match' => '',
        'If-Unmodified-Since' => '',
        'Range' => '',
        'CanonicalizedHeaders' => '',
        'CanonicalizedResource' => '',
        'CanonicalizedQuery' => '');
    protected $requestHeaders = array();
    protected $uri;

    /**
     * AzureHeaders constructor requires API version, URI, and HTTP method because it's the minimum required to generate
     * a valid request.
     *
     * @param string $apiVersion info can be found here: http://bit.ly/2gzrbjf
     * @param string $uri
     * @param string $httpMethod like PUT, GET, DELETE
     *
     */
    function __construct( $apiVersion, $uri, $httpMethod )
    {
        self::set_header( 'httpMethod', strtoupper($httpMethod) );
        self::set_header( 'x-ms-version', $apiVersion );
        self::set_header( 'x-ms-client-request-id', uniqid('', true) );
        self::set_header( 'Date', gmdate('D, d M Y H:i:s T', time()) );
        self::set_uri( $uri );
    }

    /**
     * Sets a new or updates an existing header
     *
     * @param $header
     * @param $value
     *
     * @return bool returns true on update
     */
    public function set_header($header, $value)
    {
        if (array_key_exists($header, $this->authArray)) {
            $this->authArray[$header] = $value;
            return true;
        }

        $this->requestHeaders[$header] = $value;
        self::make_canonicalized_headers();
        return true;
    }

    /**
     * Returns the value for a given header name
     *
     * @param $header
     *
     * @return bool|string returns string of value if it exists, false if the header does not exist
     */
    public function get_header($header)
    {
        if (array_key_exists($header, $this->authArray)) {
            return $this->authArray[$header];
        }

        if (array_key_exists($header, $this->requestHeaders)) {
            return $this->requestHeaders[$header];
        }

        return false;
    }

    /**
     * Sets the URI for the request, which is needed to generate an authorization header
     *
     * @param $uri
     *
     */
    public function set_uri($uri)
    {
        $this->uri = $uri;
        self::make_canonicalized_resource();
        self::make_canonicalized_query();
    }

    /**
     * Generates the canonicalized string of x-ms- headers for signature creation
     *
     * @return bool true on success
     */
    protected function make_canonicalized_headers()
    {
        foreach ($this->requestHeaders as $key => $value) {
            if (preg_match('/^x-ms-[a-zA-Z]+/', $key)) {
                $headers[$key] = $value;
            }
        }

        $headers = ksort($headers);
        $canonicalized = '';
        foreach ($headers as $key => $value) {
            $canonicalized .= $key . ':' . $value . '\n';
        }

        self::set_header('CanonicalizedHeaders', $canonicalized);
        return true;
    }

    /**
     * Generates the canonicalized resource URI which is just the URI with the domain removed and no query parameters
     *
     * @return bool true on success
     */
    protected function make_canonicalized_resource()
    {
        self::set_header( 'CanonicalizedResource', strstr(strstr($this->uri, '/'), '?', true) );
        return true;
    }

    /**
     * Generates the canonicalized query parameters
     *
     * @return bool true on success
     */
    protected function make_canonicalized_query()
    {
        $parameters = explode( '&', ltrim(strstr($this->uri, '?'), '?') );
        $canonicalized = '';
        foreach( $parameters as $parameter )
        {
            $canonicalized .= str_replace( '=', ':', $parameter ) . '\n';
        }

        // We can't do a rtrim because we have a multicharacter string to trim off
        self::set_header( 'CanonicalizedQuery', substr($canonicalized, 0, strrpos($canonicalized,'\n')) );
        return true;
    }

    /**
     * Generates the authorization header required for sending a request to Azure (see http://bit.ly/2fdFg8E for info)
     *
     * @param $account
     * @param $key
     *
     * @return string Returns string ready for insert as header for false if minimum info doesn't exist
     */
    public function make_authorization_header ( $account, $key )
    {
        $stringToSign = implode( '\n', $this->authArray );

        return self::AUTH_TYPE . ' ' . $account . ':' . base64_encode(hash_hmac('sha256', $stringToSign, $key, true));
    }
}