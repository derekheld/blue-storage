<?php
/**
 * Used for connecting to the Azure Storage REST API. Only supports the blob service.
 *
 * @package   Blue Storage
 * @author    Derek Held
 * @link      https://wordpress.org/plugins/blue-storage/
 */

namespace BlueStorage;

class AzureStorageClient
{
    protected $account = '';
    protected $container = '';
    protected $key = '';

    const BLOB_URL = '.blob.core.windows.net';
    const AUTH_TYPE = 'SharedKey';
    const X_MS_VERSION = '2015-12-11';

    /**
     * Constructor
     *
     * @param string $account
     *
     * @param string $key
     *
     * @param string $container
     */
    function __construct( $account, $key, $container )
    {
        $this->class_set_account( $account );
        $this->class_set_key( $key );
        $this->class_set_container( $container );
    }

    public function class_set_account( $account )
    {
        if( preg_match( '[a-z0-9]*' ) )
        {
            $this->account = $account;
        }
    }

    public function class_get_account( )
    {
        return $this->account;
    }

    public function class_set_container( $container )
    {
        $this->container = $container;
    }

    public function class_set_key( $key )
    {
        $this->key = $key;
    }

    public function class_get_key( )
    {
        return $this->key;
    }

    public function get_uri( $blobName = '', $parameters = array() )
    {
        $uri = $this->account.'.'.self::BLOB_URL.'/'.$this->container;

        if( !empty($blobName) )
        {
            $uri .= '/' . $blobName;
        }

        if( !empty($parameters) )
        {
            $uri .= '?';
            foreach( $parameters as $key => $value )
            {
                $uri .= $key . '=' . $value . '&';
            }

            $uri = rtrim( $uri, '&' );
        }

        return $uri;
    }

    /**
     * Generates the canonicalized string of x-ms- headers for signature creation
     *
     * @param array $headers key => value pairs of all x-ms- headers specified in the request
     *
     * @return string canonicalized headers ready for signature generation
     */
    protected function get_canonicalized_headers( $headers )
    {
        // Azure requires the headers to be chained in lexicographical order
        $headers['x-ms-version'] = self::X_MS_VERSION;
        $headers = ksort( $headers );
        $canonicalized = '';
        foreach( $headers as $key => $value )
        {
            $canonicalized .= $key . ':' . $value . '\n';
        }

        return $canonicalized;
    }

    /**
     * Generates the canonicalized resource URI which is just the URI with the domain removed and no query parameters
     *
     * @param string $uri the full request URI
     *
     * @return string canonicalized URI ready for signature generation
     */
    protected function get_canonicalized_resource ( $uri )
    {
        return strstr( strstr($uri, '/'), '?', true );
    }

    /**
     * Generates the canonicalized query parameters
     *
     * @param string $uri the full request URI
     *
     * @return string canonicalized query parameters ready for signature generation
     */
    protected function get_canonicalized_query ( $uri )
    {
        $parameters = explode( '&', ltrim(strstr($uri, '?'), '?') );
        $canonicalized = '';
        foreach( $parameters as $parameter )
        {
            $canonicalized .= str_replace( '=', ':', $parameter ) . '\n';
        }

        // We can't do a rtrim because we have a multicharacter string to trim off
        return substr( $canonicalized, 0, strrpos($canonicalized,'\n') );
    }

    /**
     * Uploads a block as part of a blob
     *
     * @param string $blobName SQL query result object
     * @param $blockID
     * @param mixed $content Whatever content is to be uploaded
     * @return bool true on success, false on error
     *
     * @throws \Exception
     */
    protected function put_block( $blobName, $blockID, $content )
    {
        //Send a block to Azure to be committed as part of a blob
        $uri = self::get_uri( $blobName, array('comp' => 'block', 'blockid' => $blockID) );

        //Prepare all headers to be used for the request and for generating the signature
        $requestID = uniqid('', true);
        $requestHeaders = $this->create_request_array();
        $requestHeaders['httpMethod'] = 'PUT';
        $requestHeaders['Date'] = gmdate( 'D, d M Y H:i:s T', time() );
        $requestHeaders['Content-MD5'] = md5( $content );
        $requestHeaders['Content-Length'] = strlen( $content );
        $requestHeaders['CanonicalizedHeaders'] = $this->get_canonicalized_headers(
                                                    array( 'x-ms-date' => $requestHeaders['Date'],
                                                           'x-ms-client-request-id' => $requestID) );
        $requestHeaders['CanonicalizedResource'] = $this->get_canonicalized_resource( $uri );
        $requestHeaders['CanonicalizedQuery'] = $this->get_canonicalized_query( $uri );

        $response = \Httpful\Request::put($uri)
                            ->addHeaders(
                                array(
                                    'Authorization' => $this->create_authorization_header( $requestHeaders ),
                                    'x-ms-date' => $requestHeaders['Date'],
                                    'Content-Length' => $requestHeaders['Content-Length'],
                                    'Content-MD5' => $requestHeaders['Content-MD5'],
                                    'x-ms-client-request-id' => $requestID,
                                )
                            )
                            ->body( $content )
                            ->send();
        if( $response->code != 201 )
        {
            throw new \Exception( 'Unable to put block. Request ID: '.$requestID, $response->code );
        }
        else
        {
            return true;
        }
    }

    /**
     * Commits a list of blocks for a given blob
     *
     * @param string $blobName SQL query result object
     *
     * @param array $blobList List of UUIDs for blocks
     *
     * @return bool true on success, false on error
     */
    protected function put_block_list( $blobName, $blockList )
    {

    }

    /**
     * Takes a blob name and makes sure it is unique
     *
     * @param string $blobName The potentially non-unique blob name
     *
     * @return string unique blob name
     */
    public function unique_blob_name( $blobName )
    {

    }

    /**
     * Checks if a blob with the given name already exists
     *
     * @param string $blobName The blob name
     *
     * @return bool true if it exists, false if it does not
     */
    public function blob_exists( $blobName )
    {
        $parameters = array( 'comp' => 'metadata' );
        $response = \Httpful\Request::get( $this->get_uri($blobName,$parameters) )
                        ->send();

        if( $response->code = 404 )
        {
            return $blobName;
        }
    }

    // Returns a string ready to be inserted as a header.
    // Follows http://bit.ly/2fdFg8E
    // Returns false if the minimum required info isn't there
    protected function create_authorization_header ( $request_array )
    {
        // Verify we have the minimum needed
        if( $request_array['httpMethod'] == '' || $request_array['CanonicalizedHeaders'] == '' || $request_array['CanonicalizedResource'] == '' )
        {
            return false;
        }

        $stringToSign = implode( '\n', $request_array );

        return self::AUTH_TYPE . ' ' . $this->class_get_account() . ':' . base64_encode(hash_hmac('sha256', $stringToSign, $this->class_get_key(), true));
    }

    //Creates empty array with all header options for creating a valid request
    protected function create_request_array ( )
    {
        return array( 'httpMethod' => '',
                        'Content-Encoding' => '',
                        'Content-Language' => '',
                        'Content-Length' => '',
                        'Content-MD5' => '',
                        'Content-Type' => '',
                        'Date' => '',
                        'If-Modified-Since' => '',
                        'If-Match' => '',
                        'If-None-Match' => '',
                        'ifUnmodifiedSince' => '',
                        'If-Unmodified-Since' => '',
                        'Range' => '',
                        'CanonicalizedHeaders' => '',
                        'CanonicalizedResource' => '',
                        'CanonicalizedQuery' => '' );
    }
}