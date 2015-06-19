<?php
/**
 * @author Stephen "TheCodeAssassin" Hoogendijk
 */

namespace Leaseweb\InfluxDB;


use GuzzleHttp\Client as httpClient;
/**
 * Class Client
 *
 * @package Leaseweb\InfluxDB
 */
class Client
{
    /**
     * @var string
     */
    protected $host = '';

    /**
     * @var int
     */
    protected $port = 8086;

    /**
     * @var string
     */
    protected $username = '';

    /**
     * @var string
     */
    protected $password = '';

    /**
     * @var int
     */
    protected $timeout = 0;

    /**
     * @var bool
     */
    protected $scheme = 'http';

    /**
     * @var bool
     */
    protected $verifySSL = false;

    /**
     * @var bool
     */
    protected $useUdp = false;

    /**
     * @var int
     */
    protected $udpPort = 4444;

    /**
     * @var
     */
    protected $baseURI;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * @var array
     */
    protected $options = array();

    /**
     * @param string $host
     * @param int    $port
     * @param string $username
     * @param string $password
     * @param bool   $ssl
     * @param bool   $verifySSL
     * @param int    $timeout
     *
     * @todo add UDP support
     * @todo add SSL support
     */
    public function __construct(
        $host,
        $port = 8086,
        $username = '',
        $password = '',
        $ssl = false,
        $verifySSL = true,
        $timeout = 0
//        $useUdp = false,
//        $udpPort = 4444
    )
    {

        $this->host = $host;
        $this->port = (int) $port;
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $timeout;
        $this->verifySSL = (bool) $verifySSL;

        if ($ssl) {
            $this->scheme = 'https';
            $this->options += array(
                'verify' => $verifySSL
            );
        }

        // the the base URI
        $this->setBaseURI(sprintf('%s://%s:%d', $this->scheme, $this->host, $this->port));

        $this->httpClient = new httpClient(array(
                'base_uri' => $this->getBaseURI(),
                'timeout' => $this->getTimeout()
            )
        );
    }

    /**
     * Use the given database
     *
     * @param string $name
     *
     * @return Database
     */
    public function db($name)
    {

        if (empty($name)) {
            throw new \InvalidArgumentException(sprintf('No name provided'));
        }

        return new Database($name, $this);
    }

    /**
     * Query influxDB
     *
     * @param string $database
     * @param string $query
     * @param array  $params
     *
     * @return ResultSet
     * @throws Exception
     */
    public function query($database = null, $query, $params = array())
    {

        if ($database) {
            $params += array('db' => $database);
        }

        $params = array_merge(array('q' => $query), $params);
        $options = array_merge($this->options, array('query' => $params, 'http_errors' => false));

        try {
            $response = $this->httpClient->get('query', $options);

            $raw = (string) $response->getBody();

            return new ResultSet($raw);

        } catch (\Exception $e) {
            throw new Exception(sprintf('Query has failed, exception: %s', $e->getMessage()));
        }
    }

    /**
     * @param $database
     * @param $data
     * @return bool
     * @throws Exception
     */
    public function write($database, $data)
    {
        try {
            $this->httpClient->post(
                $this->getBaseURI() . '/write?db=' . $database,
                array('body' => $data)
            );
        } catch (\Exception $e) {
            throw new Exception(sprintf('Writing has failed, exception: %s', $e->getMessage()));
        }

        return true;
    }

    /**
     * List all the databases
     */
    public function listDatabases()
    {
        $result = $this->query(null, 'SHOW DATABASES')->getPoints();

        return $this->pointsToArray($result);
    }

    /**
     * List all the users
     *
     * @return array
     * @todo implement once issue #3048 is fixed
     *
     * @throws Exception
     */
//    public function listUsers()
//    {
//        $result = $this->query(null, 'SHOW USERS')->getPoints();
//
//        return $this->pointsToArray($result);
//    }

    /**
     * Build the client from a dsn
     *
     * Example: tcp+influxdb://username:pass@localhost:8086/databasename', timeout=5
     *
     * @param string $dsn
     *
     * @todo implement this functionality
     */
//    public static function fromDSN($dsn)
//    {
//        $args  = array();
//    }

    /**
     * @return mixed
     */
    public function getBaseURI()
    {
        return $this->baseURI;
    }

    /**
     * @param mixed $baseURI
     */
    public function setBaseURI($baseURI)
    {
        $this->baseURI = $baseURI;
    }

    /**
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param int $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * @param array $points
     *
     * @return array
     */
    protected function pointsToArray(array $points)
    {
        $names = array();

        foreach ($points as $item) {
            $names[] = $item['name'];
        }

        return $names;
    }
}