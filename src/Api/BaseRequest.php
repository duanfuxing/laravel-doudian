<?php

namespace duan617\DouDian\Api;

use GuzzleHttp\Client;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;

class BaseRequest
{
    public const OAUTH_CACHE_KEY = 'doudian_oauth_token';
    public const OAUTH_CACHE_EXPIRED = 60 * 24; // 分钟 一天

    /**
     * @var array 配置参数
     */
    private $config;
    private $shop_id;

    /**
     * @var string 接口地址
     */
    private $baseUrl = 'https://openapi-fxg.jinritemai.com/';

    /**
     * @var Client
     */
    private $client;

    public function __construct(array $config, $shop_id)
    {
        $this->config = $config;
        $this->shop_id = $shop_id;
        if (!isset($config['app_key']) || !$config['app_key']) {
            throw new \InvalidArgumentException('配置有误, 请填写app_key');
        }

        if (!isset($config['app_secret']) || !$config['app_secret']) {
            throw new \InvalidArgumentException('配置有误, 请填写app_secret');
        }

        if (!isset($config['oauth_cache_expire']) || !$config['oauth_cache_expire']) {
            throw new \InvalidArgumentException('配置有误, 请填写oauth_cache_expire');
        }
        $this->client = new Client();
    }

    /**
     * 发起GET请求
     *
     * @param string $url
     * @param array $params
     * @param bool $needSign
     *
     * @return array
     * @throws InvalidArgumentException
     *
     * @throws RequestException
     */
    public function httpGet(string $url, array $params = [], bool $needSign = true): array
    {
        return $this->request('get', $url, $params, $needSign);
    }

    /**
     * 发起HTTP请求
     *
     * @param string $method
     * @param string $url
     * @param array $params
     * @param bool $needSign
     *
     * @return array
     * @throws InvalidArgumentException
     *
     * @throws RequestException
     */
    private function request(string $method, string $url, array $params = [], bool $needSign = true): array
    {
        $options = [];
        if ($needSign) {
            $params = $this->generateParams($url, $params);
        }
        $options['headers'] = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        $key = $method == 'get' ? 'query' : 'form_params';
        $options[$key] = $params;

        $response = $this->client->request($method, $this->baseUrl . $url, $options);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * 组合请求参数.
     *
     * @param string $url
     * @param array $params
     *
     * @return array
     * @throws InvalidArgumentException
     *
     * @throws RequestException
     */
    protected function generateParams(string $url, array $params): array
    {
        $url = str_replace('/', '.', $url);

        $accessToken = "";
        if (!in_array($url, ["token.create", "token.refresh"])) {
            $accessToken = $this->getAccessToken();
        };

        $public = [
            'app_key'      => $this->config['app_key'],
            'timestamp'    => date('Y-m-d H:i:s'),
            'v'            => '2',
            'method'       => $url,
            'access_token' => $accessToken,
        ];

        ksort($params);
        $param_json = json_encode((object)$params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $str = 'app_key' . $public['app_key'] . 'method' . $url . 'param_json' . $param_json . 'timestamp' . $public['timestamp'] . 'v' . $public['v'];
        $md5_str = $this->config['app_secret'] . $str . $this->config['app_secret'];
        $sign = md5($md5_str);

        return array_merge(
            $public,
            [
                'param_json' => $param_json,
                'sign'       => $sign,
            ]
        );
    }

    /**
     * 获取TOKEN.
     *
     * @return mixed|string
     * @throws InvalidArgumentException
     *
     * @throws RequestException
     */
    private function getAccessToken(): string
    {
        $oauthToken = Cache::get(self::OAUTH_CACHE_KEY . $this->shop_id, []);
        if (!$oauthToken || !$oauthToken['refresh_token']) {
            return $this->requestAccessToken();
        }

        if ($oauthToken['access_token_expired_at'] - time() <= 100 && $oauthToken['refresh_token_expired_at'] > time()) {
            return $this->updateAccessToken($oauthToken['refresh_token']);
        }

        return $oauthToken['access_token'];
    }

    /**
     * 请求TOKEN.
     *
     * @return string
     * @throws InvalidArgumentException
     *
     * @throws RequestException
     */
    private function requestAccessToken(): string
    {
        $param = [
            'app_id'     => $this->config['app_key'],
            'app_secret' => $this->config['app_secret'],
            'grant_type' => 'authorization_self',
        ];

        if ($this->shop_id) {
            $param['shop_id'] = $this->shop_id;
        }

        $response = $this->httpGet('token/create', $param, true);
        if (!$response || $response['code'] > 10000) {
            trigger_error("token/create 接口异常[{$response['code']}]");
        }
        $response['data']['access_token_expired_at'] = time() + $response['data']['expires_in'];
        $response['data']['refresh_token_expired_at'] = strtotime('+14 day');

        Cache::add(self::OAUTH_CACHE_KEY . $this->shop_id, $response['data'], $this->config['oauth_cache_expire']);

        return $response['data']['access_token'];
    }

    /**
     * 刷新TOKEN.
     *
     * @param string $refreshToken
     *
     * @return string
     * @throws InvalidArgumentException
     *
     * @throws RequestException
     */
    private function updateAccessToken(string $refreshToken): string
    {
        $param = [
            'app_id'        => $this->config['app_key'],
            'app_secret'    => $this->config['app_secret'],
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];

        $response = $this->httpGet('token/refresh', $param, true);
        if (!$response || $response['code'] > 10000) {
            trigger_error("token/refresh 接口异常[{$response['code']}]");
        }
        $response['data']['access_token_expired_at'] = time() + $response['data']['expires_in'];
        $response['data']['refresh_token_expired_at'] = strtotime('+14 day');

        Cache::add(self::OAUTH_CACHE_KEY . $this->shop_id, $response['data'], $this->config['oauth_cache_expire']);

        return $response['data']['access_token'];
    }

    /**
     * 发起POST请求
     *
     * @param string $url
     * @param array $params
     * @param bool $needSign
     *
     * @return array
     * @throws InvalidArgumentException
     *
     * @throws RequestException
     */
    public function httpPost(string $url, array $params = [], bool $needSign = true): array
    {
        return $this->request('post', $url, $params, $needSign);
    }

    public function setHttpClient($client)
    {
        $this->client = $client;

        return $this;
    }
}
