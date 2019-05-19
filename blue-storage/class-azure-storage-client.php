<?php
/**
 * Used for connecting to the Azure Storage REST API. Only supports the blob service.
 *
 * @package   Blue Storage
 * @author    Derek Held
 * @link      https://wordpress.org/plugins/blue-storage/
 */

namespace BlueStorage;
use Exception;
use Httpful\Request;

require_once( 'class-azure-headers.php' );

class AzureStorageClient
{
    const BLOB_URL = '.blob.core.windows.net';
    const X_MS_VERSION = '2018-11-09';
    const MAX_BLOCK_SIZE = 102400;
    const MAX_NUM_BLOCKS = 50000;

    protected $account = '';
    protected $container = '';
    protected $key = '';
    protected $blockSize = 0;

    /**
     * Constructor
     *
     * @param string $account
     *
     * @param string $key
     *
     * @param string $container
     *
     * @param integer $blockSize
     */
    function __construct( $account, $key, $container, $blockSize = 4096 )
    {
        if( !$this->class_set_account($account) )
        {
            return false;
        }
        if( !$this->class_set_key($key) )
        {
            return false;
        }
        if( !$this->class_set_container($container) )
        {
            return false;
        }
        if( !$this->class_set_block_size($blockSize) )
        {
            return false;
        }
    }

    /**
     * Sets a new account name as long as it is valid
     * https://docs.microsoft.com/en-us/azure/azure-resource-manager/resource-manager-storage-account-name-errors
     *
     * @param string $account
     *
     * @return boolean
     */
    public function class_set_account( $account )
    {
        if( preg_match( '/[a-z0-9]{3,24}/', $account ) )
        {
            $this->account = $account;
            return true;
        }

        return false;
    }

    /**
     * Sets a new block size
     * https://docs.microsoft.com/en-us/rest/api/storageservices/put-block
     *
     * @param integer $blockSize
     *
     * @return boolean
     */
    public function class_set_block_size( $blockSize )
    {
        if( is_int($blockSize) && $blockSize > 0 && $blockSize <= self::MAX_BLOCK_SIZE )
        {
            $this->blockSize = $blockSize;
            return true;
        }

        return false;
    }

    /**
     * Gets the block size value
     *
     * @return integer
     */
    public function class_get_block_size( )
    {
        return $this->blockSize;
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
     * https://docs.microsoft.com/en-us/rest/api/storageservices/naming-and-referencing-containers--blobs--and-metadata
     *
     * @param string $container
     *
     * @return boolean
     */
    public function class_set_container( $container )
    {
        if( preg_match( '/[a-z0-9]{1}[a-z0-9\-]{2,62}', $container ) )
        {
            // Checking for repeating dash with lookaheads or lookbehinds above was going to be a mess
            if( !preg_match('/.+--.*/', $container) )
            {
                $this->container = $container;
                return true;
            }
        }

        return false;
    }

    /**
     * Sets the secret key for accessing the service
     *
     * @param string $key
     *
     * @return boolean
     */
    public function class_set_key( $key )
    {
        if ( base64_encode(base64_decode($key, true)) === $key )
        {
            $this->key = $key;
            return true;
        }

        return false;
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
        $uri = $this->account.self::BLOB_URL.'/'.$this->container;

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

    //TODO: put_blob? Would calculate MD5, type, etc?

    public function put_block_blob( $blobName, $path, $contentMD5, $contentType, $cacheControl = NULL, $metadata = array() )
    {
        //Split up file and put blocks and then block list
        $size = filesize( $path );
        $blockList = array();

        if( $size > (self::class_get_block_size() * self::MAX_NUM_BLOCKS) )
        {
            throw new Exception( 'Block size too small to upload file ' . $path, 1 );
        }

        $handle = fopen( $path, 'rb' );
        if( $handle === false )
        {
            throw new Exception( 'Unable to open file for reading', 1 );
        }

        while( $content = fread($handle, self::class_get_block_size()) )
        {
            $blockID = self::generate_block_id();
            $blockList[] = $blockID;
            self::put_block( $blobName, $blockID, $content );
        }

        //Commit block list
        self::put_block_list( $blobName, $blockList, $cacheControl, $contentType, $contentMD5 );

        //TODO: Verify MD5 of file with blob
    }

    /**
     * Uploads a block as part of a blob
     *
     * @param string $blobName SQL query result object
     * @param $blockID
     * @param mixed $content Whatever content is to be uploaded
     * @return bool true on success, false on error
     *
     * @throws Exception
     */
    protected function put_block( $blobName, $blockID, $content )
    {
        //Send a block to Azure to be committed as part of a blob
        $uri = self::get_uri( $blobName, array('comp' => 'block', 'blockid' => $blockID) );

        //Prepare all headers to be used for the request and for generating the signature
        $headers = new AzureHeaders( self::X_MS_VERSION, $uri, 'PUT' );
        $headers->set_header( 'Content-MD5', md5($content) );
        $headers->set_header( 'Content-Length', strlen($content) );

        $response = Request::put($uri)
                            ->addHeaders( $headers->get_request_headers(self::class_get_account(), self::class_get_key()) )
                            ->body( $content )
                            ->send();
        if( $response->code != 201 )
        {
            throw new Exception( 'Unable to put block. Request ID: '.$headers->get_header( 'x-ms-client-request-id' ), $response->code );
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
     * @throws Exception
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

        $response = Request::put($uri)
                            ->addHeaders( $headers->get_request_headers(self::class_get_account(), self::class_get_key()) )
                            ->body( $content )
                            ->send();

        if( $response->code != 201 )
        {
            throw new Exception( 'Unable to put block. Request ID: '.$headers->get_header( 'x-ms-client-request-id' ), $response->code );
        }
        else
        {
            return true;
        }
    }

	/**
	 * Takes a blob name and makes sure it is unique.
	 *
	 * @param string $blobName The potentially non-unique blob name
	 *
	 * @return string unique blob name
     * 
	 * @throws Exception
	 */
    public function get_unique_blob_name( $blobName )
    {
        $newName = $blobName;
	    $pathParts = pathinfo( $blobName );
        while ( $this->blob_exists($newName) )
        {
            // Insert a number between filename and extension or at the end of the name if there is no extension
	        $counter++;
	        if ( $pathParts['filename'] == '' || $pathParts['filename'] == $pathParts['basename'] )
            {
                $newName = $pathParts['dirname'].'/'.$pathParts['basename'].$counter;
            }
            else
            {
                $newName = $pathParts['dirname'].'/'.$pathParts['filename'].$counter.$pathParts['extension'];
            }
        }

        return $newName;
    }

    /**
     * Checks if a blob with the given name already exists
     *
     * @param string $blobName The blob name
     *
     * @return bool true if it exists, false if it does not
     *
     * @throws Exception
     */
    public function blob_exists( $blobName )
    {
        $parameters = array( 'comp' => 'metadata' );

        $response = Request::get($this->get_uri($blobName, $parameters))
                    ->send();

        if( $response->code == 404 )
        {
            return false;
        }
        elseif( $response->code == 200 )
        {
            return true;
        }
        else
        {
            throw new Exception( 'Unable to check for blob name. Request ID: '.$headers->get_header( 'x-ms-client-request-id' ), $response->code );
        }
    }

    /**
     * Generates a base64 encoded random ID to use as a block ID
     *
     * @param string $blobName The blob name
     *
     * @return bool true if it exists, false if it does not
     *
     * @throws Exception
     */
    protected function generate_block_id( )
    {
        return base64_encode( uniqid( '', true ) );
    }
}