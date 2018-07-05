<?php

namespace Drewsauce\StockSync\Aws;

use Aws\S3\S3Client;
use Aws\Credentials\Credentials;
use Aws\S3\Exception\S3Exception;
use Drewsauce\StockSync\Exception\InvalidBucketException;
use Drewsauce\StockSync\Exception\InvalidPathException;

/**
 * Class Handler
 * @package Drewsauce\StockSync\Aws
 */
class Handler
{
    const AWS_VERSION = 'latest';

    const CSV_REGEX = '/([\w|\s|\d]+.csv)/';

    /**
     * @var \Drewsauce\StockSync\Loader\Config
     */
    private $config;

    /**
     * Bucket for AWS S3 service
     * @var string
     */
    private $bucket;

    /**
     * Region for AWS S3 service
     * @var string
     */
    private $region;

    /**
     * @var \Aws\Credentials\Credentials
     */
    private $credentials;

    /**
     * @var \Aws\S3\S3Client
     */
    private $client;

    /**
     * Local incoming folder for externally generated EDI files
     * @var string
     */
    private $incoming;

    /**
     * Incoming AWS Key
     * @var string
     */
    private $awsIncoming;


    private $logger;

    /**
     * Handler constructor.
     * @param \Drewsauce\StockSync\Loader\Config $config
     * @param \Drewsauce\StockSync\Logger\Logger $logger
     * @throws InvalidPathException
     * @throws \Drewsauce\StockSync\Exception\EmptyConfigException
     */
    public function __construct(
        \Drewsauce\StockSync\Loader\Config $config,
        \Drewsauce\StockSync\Logger\Logger $logger
    ) {
        $this->config        = $config;
        $this->bucket        = $this->setBucket();
        $this->region        = $this->setRegion();
        $this->credentials   = $this->setCredentials();
        $this->incoming      = $this->setLocalIncoming();
        $this->awsIncoming   = $this->setAwsIncoming();
        $this->client        = $this->setClient();
        $this->logger        = $logger;
    }

    /**
     * @throws InvalidBucketException
     */
    public function run()
    {
        if ($this->bucketExists()) {
            $this->downloadFiles();
        } else {
            throw new InvalidBucketException('No bucket found at endpoint');
        }
    }

    /**
     * Get a listing of objects on AWS S3 service.
     * Download the objects locally and if all checks out delete the object on service.
     * @throws S3Exception
     */
    private function downloadFiles()
    {
        $results = [];
        $objects = $this->client->listObjects([
            'Bucket' => $this->bucket,
            'Prefix' => $this->awsIncoming
        ]);
        $files = $this->getIncomingFiles($objects);

        if ($files) {
            // Download the files listed in the outgoing folder.
            foreach ($files as $filename) {
                try {
                    $results[] = $this->client->getObject([
                        'Bucket' => $this->bucket,
                        'Key'    => $this->awsIncoming . '/' . $filename
                    ]);
                } catch (S3Exception $e) {
                    throw $e;
                }
            }

            // Write the resulting objects to a local incoming
            // folder to be processed by the edi module.
            foreach ($results as $result) {
                // Get local write path and file contents form result object.
                $filename = $this->getFileName($result);
                $path     = $this->getWritePath($filename);
                $contents = $this->getContent($result);

                try {
                    // Write file contents locally.
                    $file = fopen($path, 'w');
                    fwrite($file, $contents);
                    fclose($file);
                } catch (S3Exception $e) {
                    throw $e;
                }

                // Ensure the file has contents, if not then delete
                // the local copy and attempt again on next cron run.
                if (!empty(file_get_contents($path))) {
                    $this->client->deleteObject([
                        'Bucket' => $this->bucket,
                        'Key'    => $this->awsIncoming . '/' . $filename
                    ]);
                } else {
                    unlink($path);
                }
            }
        }
    }

    /**
     * Checks that a bucket exists on the Amazon S3 service.
     *
     * @return bool
     */
    private function bucketExists()
    {
        $buckets = $this->client->listBuckets();

        foreach ($buckets as $bucketarray) {
            foreach($bucketarray as $bucket) {
                $name = $bucket['Name'];
                if ($name == $this->bucket) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param $path
     * @return bool
     * @throws InvalidPathException
     */
    private function pathExists($path)
    {
        $fullPath = $this->path(BP . $path);

        if (empty($path)) {
            throw new InvalidPathException($fullPath .': path is empty.');
        }

        if (!is_dir($fullPath)) {
            throw new InvalidPathException($fullPath .': path does not exist.');
        }

        if (!is_writable($fullPath)) {
            throw new InvalidPathException($fullPath .': path is not writable.');
        }

        return true;
    }

    /**
     * Gets absolute path, cross-platform compatible.
     *  - Adds trailing slash.
     *
     * @param $path
     * @return string
     */
    private function path($path) : string
    {
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $absolutes = array();

        foreach ($parts as $part) {
            if ('chroot' == $part) continue;
            if ('.' == $part) continue;
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }

        $return_path = DIRECTORY_SEPARATOR.rtrim(implode(DIRECTORY_SEPARATOR, $absolutes), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return $return_path;
    }

    /**
     * Returns an array of filenames located on AWS S3 service.
     *
     * @param $objects
     * @return array
     */
    private function getIncomingFiles($objects) : array
    {
        $files = [];
        $contents = $objects['Contents'];

        foreach ($contents as $content) {
            if (preg_match(self::CSV_REGEX, $content['Key'], $matches)) {
                $files[] = $matches[1];
            }
        }

        return $files;
    }

    /**
     * Get local write path for files.
     *
     * @param $filename
     * @return string
     */
    private function getWritePath($filename) : string
    {
        return $this->incoming . $filename;
    }

    /**
     * Returns the content stream for the file from the result object.
     *
     * @param \Aws\Result $result
     * @return \GuzzleHttp\Psr7\Stream
     */
    private function getContent($result) : \GuzzleHttp\Psr7\Stream
    {
        return $result['Body'];
    }

    /**
     * @param \Aws\Result $result
     * @return string
     */
    private function getFileName($result) : string
    {
        preg_match(self::CSV_REGEX, $result['@metadata']['effectiveUri'], $matches);
        return $matches[1];
    }

    /**
     * @return Credentials
     * @throws \Drewsauce\StockSync\Exception\EmptyConfigException
     */
    private function setCredentials() : Credentials
    {
        $key = $this->config->getKey();
        $secret = $this->config->getSecret();

        $credentials = new Credentials($key, $secret);

        return $credentials;
    }

    /**
     * @return S3Client
     */
    private function setClient() : S3Client
    {
        return new S3Client([
            'version'     => self::AWS_VERSION,
            'region'      => $this->region,
            'credentials' => $this->credentials
        ]);
    }

    /**
     * @return string
     * @throws \Drewsauce\StockSync\Exception\InvalidPathException
     * @throws \Drewsauce\StockSync\Exception\EmptyConfigException
     */
    private function setLocalIncoming() : string
    {
        $incoming = $this->config->getIncomingFolder();

        if ($this->pathExists($incoming)) {
            return $this->path(BP . $incoming);
        } else {
            throw new InvalidPathException($incoming . ' not found');
        }
    }

    /**
     * @return string
     * @throws \Drewsauce\StockSync\Exception\InvalidPathException
     * @throws \Drewsauce\StockSync\Exception\EmptyConfigException
     */
    private function setArchive() : string
    {
        $archive = $this->config->getArchiveFolder();

        if ($this->pathExists($archive)) {
            return $this->path(BP . $archive);
        } else {
            throw new InvalidPathException($archive  . ' is not found.');
        }
    }

    /**
     * @return string
     * @throws \Drewsauce\StockSync\Exception\EmptyConfigException
     */
    private function setBucket() : string
    {
        return $this->config->getBucket();
    }

    /**
     * @return string
     * @throws \Drewsauce\StockSync\Exception\EmptyConfigException
     */
    private function setRegion() : string
    {
        return $this->config->getRegion();
    }

    /**
     * @return string
     * @throws \Drewsauce\StockSync\Exception\EmptyConfigException
     */
    private function setAwsIncoming() : string
    {
        return $this->config->getAwsIncoming();
    }
}