<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Cache;
use Psr\Http\Message\RequestInterface;

class APIClient
{
    /**
     * @param array $options
     * @return Client
     */
    private static function createClient(array $options = []): Client
    {
        $guzzleOptions = array_merge([
            'base_uri' => 'https://www.classicms.net',
            'verify' => false,
        ], $options);
        return new Client($guzzleOptions);
    }

    /**
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private static function getAccessToken(): string
    {
        $accessToken = Cache::get('cms_access_token');
        if (is_null($accessToken)) {
            $client = self::createClient();
            $response = $client->post('oauth/token', [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => config('services.classicms.client_id'),
                    'client_secret' => config('services.classicms.client_secret'),
                ],
            ]);
            $contents = $response->getBody()->getContents();
            $json = json_decode($contents, true);
            $expiresIn = $json['expires_in'];
            $accessToken = $json['access_token'];
            Cache::put('cms_access_token', $accessToken, $expiresIn);
        }
        return $accessToken;
    }

    /**
     * @return Client
     */
    public static function getAPIClient(): Client
    {
        $handler = HandlerStack::create();
        $handler->push(Middleware::mapRequest(function (RequestInterface $request) {
            return $request->withHeader('Authorization', 'Bearer ' . self::getAccessToken());
        }));
        return self::createClient([
            'base_uri' => 'https://www.classicms.net/api/',
            'handler' => $handler,
        ]);
    }

    /**
     * @return array<int, int>
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getExpTable(): array
    {
        $client = self::getAPIClient();
        $response = $client->get('v1/exp-table');
        $contents = $response->getBody()->getContents();
        return json_decode($contents, true);
    }
}
