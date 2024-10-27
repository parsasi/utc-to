<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IpInfoDto
{
    public string $timezone;
    public string $country;

    public function __construct(string $timezone, string $country)
    {
        $this->timezone = $timezone;
        $this->country = $country;
    }
}

class MainController extends Controller
{
    public function main(Request $request)
    {
        $clientIp = $this->getRequestIp($request);
        $ipInfo = $this->getIpInfo($clientIp);

        $time = $request->get('t', Carbon::now()->toISOString());

        $carbonTime = Carbon::parse($time, 'UTC');
        $convertedTime = $carbonTime->clone()->setTimezone($ipInfo->timezone);

        $response = [
            'timezone' => $ipInfo->timezone,
            'time' => $convertedTime,
            'utc' => $carbonTime
        ];

        if (str_contains($request->header('Accept'), 'html')) {
            return view('welcome', $response);
        }

        return $response;
    }

    protected function getIpInfo(string $ip): IpInfoDto
    {
        $cache_key = "IP_INFO_CACHE_" . $ip;
        $response_json = null;
        if (Cache::has($cache_key)) {
            $response_json = Cache::get($cache_key);
        } else {
            $response = $this->getBaseClient()->get($ip);
            if ($response->ok()) {
                $response_json = $response->json();
                Cache::put($cache_key, $response_json, 3000);
            } else {
                throw new \Exception('IP Call failed');
            }
        }

        return new IpInfoDto($response_json['timezone'], $response_json['countryCode']);
    }

    protected function getBaseClient(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl('http://ip-api.com/json/');
    }

    protected function getRequestIp(Request $request): string
    {
        if (config('app.env') === 'local') {
            return '38.180.12.211';
        } else {
            return $request->ip();
        }
    }
}
