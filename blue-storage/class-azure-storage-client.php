<?php
/**
 * Used for connecting to the Azure Storage REST API. Only supports the blob service.
 *
 * @package   Blue Storage
 * @author    Derek Held
 * @link      https://wordpress.org/plugins/blue-storage/
 */

namespace BlueStorage;
require_once( 'class-azure-headers.php' );

class AzureStorageClient
{
    const BLOB_URL = '.blob.core.windows.net';
    const X_MS_VERSION = '2015-12-11';

    protected $account = '';
    protected $container = '';
    protected $key = '';

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

    /**
     * Sets a new account name as long as it is valid
     *
     * @param string $account
     *
     */
    public function class_set_account( $account )
    {
        if( preg_match( '/[a-z0-9]*/', $account ) )
        {
            $this->account = $account;
        }
    }

    /**
     * Returns the account name set when instantiated
     *
     * @return string
     */
    public function class_get_account( )
    {
        return $this->account;
    }

    /**
     * Sets the container for which operations will be performed on
     *
     * @param string $container
     */
    public function class_set_container( $container )
    {
        $this->container = $container;
    }

    /**
     * Sets the secret key for accessing the service
     *
     * @param string $key
     */
    public function class_set_key( $key )
    {
        $this->key = $key;
    }

    /**
     * Returns the secret key for accessing the service
     *
     * @return string
     */
    public function class_get_key( )
    {
        return $this->key;
    }

    /**
     * Creates a URI for submitting a request to the Azure API, it purposefully does not set the scheme
     *
     * @param string $blobName not required when performing an operation against a container
     * @param array $parameters request may or may not have these
     *
     * @return string completed URI
     */
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
    protected function put_block( $blobName, $blockID, $content )
    {
        //Send a block to Azure to be committed as part of a blob
        $uri = self::get_uri( $blobName, array('comp' => 'block', 'blockid' => $blockID) );

        //Prepare all headers to be used for the request and for generating the signature
        $headers = new AzureHeaders( self::X_MS_VERSION, $uri, 'PUT' );
        $headers->set_header( 'Content-MD5', md5($content) );
        $headers->set_header( 'Content-Length', strlen($content) );

        $response = \Httpful\Request::put($uri)
                            ->addHeaders(
                                array(
                                    'Authorization' => $headers->make_authorization_header( self::class_get_account(), self::class_get_key() ),
                                    'Date' => $headers->get_header( 'Date' ),
                                    'Content-Length' => $headers->get_header( 'Content-Length' ),
                                    'Content-MD5' => $headers->get_header( 'Content-MD5' ),
                                    'x-ms-client-request-id' => $headers->get_header( 'x-ms-client-request-id' ),
                                )
                            )
                            ->body( $content )
                            ->send();
        if( $response->code != 201 )
        {
            throw new \Exception( 'Unable to put block. Request ID: '.$headers->get_header( 'x-ms-client-request-id' ), $response->code );
        }
        else
        {
            return true;
        }
    }

    /**
     * Commits a list of blocks for a given blob
     *
     * @param string $blobName unique blob name for which has blocks in uncommitted state to be committed
     * @param array $blockList List of UUIDs for blocks
     *
     * @return bool true on success, false on error
     *
     * @throws \Exception
     */
    protected function put_block_list( $blobName, $blockList, $cacheControl, $contentType, $contentMD5 )
    {
        $uri = self::get_uri( $blobName, array('comp' => 'block') );

        $content = '<?xml version="1.0" encoding="utf-8"?><BlockList><Uncommitted></Uncommitted>';
        $content .= implode( '</Uncommitted><Uncommitted>', $blockList );
        $content = substr( $content, 0, strrpos($content,'<Uncommitted>') ) . '</BlockList>';

        $headers = new AzureHeaders( self::X_MS_VERSION, $uri, 'PUT' );
        $headers->set_header( 'Content-MD5', md5($content) );
        $headers->set_header( 'Content-Length', strlen($content) );
        $headers->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
        $headers->set_header( 'x-ms-cache-control', $cacheControl );
        $headers->set_header( 'x-ms-blob-content-type', $contentType );
        $headers->set_header( 'x-ms-blob-content-md5', $contentMD5 );

        $response = \Httpful\Request::put($uri)
                            ->addHeaders( $headers->get_all_set_headers() )
                            ->body( $content )
                            ->send();

        if( $response->code != 201 )
        {
            throw new \Exception( 'Unable to put block. Request ID: '.$headers->get_header( 'x-ms-client-request-id' ), $response->code );
        }
        else
        {
            return true;
        }
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
}