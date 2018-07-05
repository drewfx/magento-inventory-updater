<?php

namespace Drewsauce\StockSync\Loader;

use Drewsauce\StockSync\Exception\EmptyConfigException;

class Config
{
    private $key;
    private $secret;
    private $bucket;
    private $region;
    private $awsIncoming;
    private $incoming;
    private $archive;

    /**
     * Config constructor.
     * @throws EmptyConfigException
     */
    public function __construct()
    {
        $content = file_get_contents( __DIR__ . './../config.json');

        if ($content == False){
            throw new EmptyConfigException('There was an error when trying to access the config.json file');
        }

        $json_data = (object)json_decode($content,true);
        if (empty($json_data)){
            throw new EmptyConfigException('The config.json file that was retrieved was empty.');
        }

        $env = getenv('ENVIRONMENT');

        if ($env == 'development') {
            $this->key = $json_data->development['creds']['key'];
            $this->secret = $json_data->development['creds']['secret'];
            $this->bucket = $json_data->development['aws']['bucket'];
            $this->region = $json_data->development['aws']['region'];
            $this->awsIncoming = $json_data->development['aws']['incoming'];
            $this->incoming = $json_data->development['folders']['incoming'];
            $this->archive = $json_data->development['folders']['archive'];
        } else {
            $this->key = $json_data->local['creds']['key'];
            $this->secret = $json_data->local['creds']['secret'];
            $this->bucket = $json_data->local['aws']['bucket'];
            $this->region = $json_data->local['aws']['region'];
            $this->awsIncoming = $json_data->local['aws']['incoming'];
            $this->incoming = $json_data->local['folders']['incoming'];
        }
    }

    /**
     * @return mixed
     * @throws EmptyConfigException
     */
    public function getKey()
    {
        if (empty($this->key)) {
            throw new EmptyConfigException('Config Key is either empty or NULL.');
        }
        return $this->key;
    }

    /**
     * @return mixed
     * @throws EmptyConfigException
     */
    public function getSecret()
    {
        if (empty($this->secret)) {
            throw new EmptyConfigException('Config Secret is either empty or NULL.');
        }
        return $this->secret;
    }

    /**
     * @return mixed
     * @throws EmptyConfigException
     */
    public function getAwsIncoming()
    {
        if (empty($this->awsIncoming)) {
            throw new EmptyConfigException('Config Oracle is either empty or NULL.');
        }
        return $this->awsIncoming;
    }

    /**
     * @return mixed
     * @throws EmptyConfigException
     */
    public function getIncomingFolder()
    {
        if (empty($this->incoming)) {
            throw new EmptyConfigException('Config Incoming is either empty or NULL.');
        }
        return $this->incoming;
    }

    /**
     * @return mixed
     * @throws EmptyConfigException
     */
    public function getBucket()
    {
        if (empty($this->bucket)) {
            throw new EmptyConfigException('Config bucket is either empty or NULL.');
        }
        return $this->bucket;
    }

    /**
     * @return mixed
     * @throws EmptyConfigException
     */
    public function getRegion()
    {
        if (empty($this->region)) {
            throw new EmptyConfigException('Config region is either empty or NULL.');
        }
        return $this->region;
    }

    /**
     * @return mixed
     * @throws EmptyConfigException
     */
    public function getArchiveFolder()
    {
        if (empty($this->secret)) {
            throw new EmptyConfigException('Config Archive is either empty or NULL.');
        }
        return $this->archive;
    }
}