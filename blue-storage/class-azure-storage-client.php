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
    private $account = '';
    private $container = '';
    private $key = '';

    const BLOB_URL = '.blob.core.windows.net';

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
     * Uploads a block as part of a blob
     *
     * @param string $blobName SQL query result object
     * @param $blockID
     * @param mixed $content Whatever content is to be uploaded
     * @return bool true on success, false on error
     *
     * @throws \Exception
     */
    private function put_block( $blobName, $blockID, $content )
    {
        //Send a block to Azure to be committed as part of a blob
        $uri = self::get_uri( $blobName, array('comp' => 'block', 'blockid' => $blockID) );
        $requestID = uniqid('', true);
        $date = gmdate( 'D, d M Y H:i:s T', time() );
        $response = \Httpful\Request::put($uri)
                            ->addHeaders(
                                array(
                                    'Authorization' => $this->create_signature(),
                                    'x-ms-date' => $date,
                                    'Content-Length' => strlen( $content ),
                                    'Content-MD5' => md5( $content ),
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
    private function put_block_list( $blobName, $blockList )
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
    // Follows http://bit.ly/1YdhvsY
    // Returns false if the minimum required info isn't there
    private function create_signature ( $auth_array )
    {
        // Verify we have the minimum needed
        if( $auth_array['httpMethod'] == '' || $auth_array['CanonicalizedHeaders'] == '' || $auth_array['CanonicalizedResource'] == '' )
        {
            return false;
        }

        $stringToSign = $auth_array['httpMethod'] . '\n' .
                        $auth_array['Content-Encoding'] . "\n" .
                        $auth_array['Content-Language'] . "\n" .
                        $auth_array['Content-Length'] . "\n" .
                        $auth_array['Content-MD5'] . '\n' .
                        $auth_array['Content-Type'] . "\n" .
                        $auth_array['Date'] . "\n" .
                        $auth_array['If-Modified-Since'] . "\n" .
                        $auth_array['If-Match'] . "\n" .
                        $auth_array['If-None-Match'] . "\n" .
                        $auth_array['If-Unmodified-Since'] . "\n" .
                        $auth_array['Range'] . "\n" .
                        $auth_array['CanonicalizedHeaders'] . '\n' .
                        $auth_array['CanonicalizedResource'];

        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $this->class_get_key(), true));

        return $signature;
    }

    //Creates empty array with all keys needed to generate a request signature.
    private function create_auth_array ( )
    {
        return array( 'httpMethod' => '',
                        'contentEncoding' => '',
                        'contentLanguage' => '',
                        'contentLength' => '',
                        'contentMD5' => '',
                        'contentType' => '',
                        'date' => '',
                        'ifModifiedSince' => '',
                        'ifMatch' => '',
                        'ifNoneMatch' => '',
                        'ifUnmodifiedSince' => '',
                        'range' => '',
                        'CanonicalizedHeaders' => '',
                        'CanonicalizedResource' => '' );
    }
}