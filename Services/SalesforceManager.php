<?php

/**
 * PROJECT: CoffeeBikeSalesforceRESTBundle
 *
 * IDE: IntelliJ IDEA
 * User: dambacher
 * Date: 15.02.17
 * Time: 16:27
 *
 * @author Jonas Dambacher <jonas.dambacher@coffee-bike.com>
 */

namespace CoffeeBike\SalesforceRESTBundle\Services;

use Circle\RestClientBundle\Exceptions\CurlException;
use Circle\RestClientBundle\Services\RestClient;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class SalesforceManager
{
    /**
     * @var RestClient
     */
    private $rest;
    private $credentials;

    /**
     * SalesforceManager constructor.
     *
     * @param RestClient $restClient
     * @param            $instance
     * @param            $username
     * @param            $password
     * @param            $token
     *
     * @internal param RestClient $client
     */
    public function __construct(RestClient $restClient, $username, $password, $token, $client_id, $client_secret)
    {
        $this->rest = $restClient;
        $this->credentials = array(
            'username' => $username,
            'password' => $password,
            'token' => $token,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
        );
    }

    public function findBy($model, array $fields, array $where = null)
    {
        $fields = implode(", ", $fields);

        if ($where) {
            $strWhere = '';
            foreach ($where as $key => $value) {
                $strWhere .= $key . "='" . $value . "'";
            }
            $query = sprintf('SELECT %s FROM %s WHERE %s', $fields, $model, $strWhere);
        } else {
            $query = sprintf('SELECT %s FROM %s', $fields, $model);
        }

        return $this->query($query);
    }

    public function getApiLimit()
    {
        return $this->request('limits', 'GET')->DailyApiRequests;
    }

    public function query($query)
    {
        $uri = 'query?q='.urlencode($query);

        return $this->request($uri, 'GET');
    }

    /**
     * Send requests to Salesforce
     *
     * @param            $uri
     * @param            $method
     * @param array|null $parameters
     * @param array|null $payload
     *
     * @return mixed
     */
    private function request($uri, $method, array $parameters = null, array $payload = null) {
        $session = $this->authenticate();

        switch ($method) {
            case 'GET':
                $response = $this->rest->get($session->instance_url.'/services/data/v39.0/'.$uri, array(CURLOPT_HTTPHEADER => ['Authorization: '.$session->token_type.' '.$session->access_token]));
                break;
        }

        if (isset($response) && $response->getStatusCode() == 200) {
            return json_decode($response->getContent());
        }
    }

    private function authenticate()
    {
        try {
            $response = $this->rest->post(
                'https://test.salesforce.com/services/oauth2/token',
                sprintf(
                    'grant_type=password&client_id=%s&client_secret=%s&username=%s&password=%s%s',
                    $this->credentials['client_id'],
                    $this->credentials['client_secret'],
                    $this->credentials['username'],
                    $this->credentials['password'],
                    $this->credentials['token']
                ),
                array(CURLOPT_HEADER => true)
            );
        } catch (CurlException $e) {
            throw new AuthenticationException("Couldn't authenticate at Salesforce. Please check your credentials!");
        }

        if ($response->getStatusCode() == 200) {
            $json = json_decode($response->getContent());

            return $json;
        } else {
            throw new AuthenticationException("Couldn't authenticate at Salesforce. Please check your credentials!");
        }

    }
}