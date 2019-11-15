<?php

namespace Bankbca;

use Carbon\Carbon;
use Unirest\Request;
use Unirest\Request\Body;

class BcaHttp
{
    public static $VERSION = '1.0.0';
    private static $timezone = 'Asia/Jakarta';
    private static $port = 443;
    private static $hostName = 'sandbox.bca.co.id';
    private static $scheme = 'https';
    private static $timeOut = 60;

    private static $curlOptions = array(
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSLVERSION => 6,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60
    );

    protected $settings = array(
        'corp_id' => '',
        'client_id' => '',
        'client_secret' => '',
        'api_key' => '',
        'secret_key' => '',
        'curl_options' => array(),
        // Backward compatible
        'host' => 'sandbox.bca.co.id',
        'scheme' => 'https',
        'timeout' => 60,
        'port' => 443,
        'timezone' => 'Asia/Jakarta',
        // New Options
        'options' => array(
            'host' => 'sandbox.bca.co.id',
            'scheme' => 'https',
            'timeout' => 60,
            'port' => 443,
            'timezone' => 'Asia/Jakarta'
        )
    );

    public function __construct($corp_id, $client_id, $client_secret, $api_key, $secret_key, array $options = [])
    {
        // Required parameters.
        $this->settings['corp_id'] = $corp_id;
        $this->settings['client_id'] = $client_id;
        $this->settings['client_secret'] = $client_secret;
        $this->settings['api_key'] = $api_key;
        $this->settings['secret_key'] = $secret_key;
        $this->settings['host'] = preg_replace('/http[s]?\:\/\//', '', $this->settings['host'], 1);

        foreach ($options as $key => $value) {
            if (isset($this->settings[$key])) {
                $this->settings[$key] = $value;
            }
        }

        // Setup optional scheme, if scheme is empty
        if (isset($options['scheme'])) {
            $this->settings['scheme'] = $options['scheme'];
            $this->settings['options']['scheme'] = $options['scheme'];
        } else {
            $this->settings['scheme'] = self::getScheme();
            $this->settings['options']['scheme'] = self::getScheme();
        }

        // Setup optional host, if host is empty
        if (isset($options['host'])) {
            $this->settings['host'] = $options['host'];
            $this->settings['options']['host'] = $options['host'];
        } else {
            $this->settings['host'] = self::getHostName();
            $this->settings['options']['host'] = self::getHostName();
        }

        // Setup optional port, if port is empty
        if (isset($options['port'])) {
            $this->settings['port'] = $options['port'];
            $this->settings['options']['port'] = $options['port'];
        } else {
            $this->settings['port'] = self::getPort();
            $this->settings['options']['port'] = self::getPort();
        }

        // Setup optional timezone, if timezone is empty
        if (isset($options['timezone'])) {
            $this->settings['timezone'] = $options['timezone'];
            $this->settings['options']['timezone'] = $options['timezone'];
        } else {
            $this->settings['timezone'] = self::getHostName();
            $this->settings['options']['timezone'] = self::getHostName();
        }

        // Setup optional timeout, if timeout is empty
        if (isset($options['timeout'])) {
            $this->settings['timeout'] = $options['timeout'];
            $this->settings['options']['timeout'] = $options['timeout'];
        } else {
            $this->settings['timeout'] = self::getTimeOut();
            $this->settings['options']['timeout'] = self::getTimeOut();
        }

        // Set Default Curl Options.
        Request::curlOpts(self::$curlOptions);

        // Set custom curl options
        if (!empty($this->settings['curl_options'])) {
            $data = self::mergeCurlOptions(self::$curlOptions, $this->settings['curl_options']);
            Request::curlOpts($data);
        }
    }

    public function getSettings()
    {
        return $this->settings;
    }

    private function ddnDomain()
    {
        return $this->settings['scheme'] . '://' . $this->settings['host'] . ':' . $this->settings['port'] . '/';
    }

    public function httpAuth()
    {
        $client_id = $this->settings['client_id'];
        $client_secret = $this->settings['client_secret'];

        $headerToken = base64_encode("$client_id:$client_secret");

        $headers = array('Accept' => 'application/json', 'Authorization' => "Basic $headerToken");

        $request_path = "api/oauth/token";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $data = array('grant_type' => 'client_credentials');
        $body = Body::form($data);
        $response = Request::post($full_url, $headers, $body);

        return $response;
    }

    public function getBalanceInfo($oauth_token, $sourceAccountId = [])
    {
        $corp_id = $this->settings['corp_id'];

        $this->validateArray($sourceAccountId);

        ksort($sourceAccountId);
        $arraySplit = implode(",", $sourceAccountId);
        $arraySplit = urlencode($arraySplit);

        $uriSign = "GET:/banking/v3/corporates/$corp_id/accounts/$arraySplit";
        $isoTime = self::generateIsoTime();
        $authSignature = self::generateSign($uriSign, $oauth_token, $this->settings['secret_key'], $isoTime, null);

        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $this->settings['api_key'];
        $headers['X-BCA-Timestamp'] = $isoTime;
        $headers['X-BCA-Signature'] = $authSignature;

        $request_path = "banking/v3/corporates/$corp_id/accounts/$arraySplit";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $data = array('grant_type' => 'client_credentials');

        $body = Body::form($data);
        $response = Request::get($full_url, $headers, $body);

        return $response;
    }

    public function getAccountStatement($oauth_token, $sourceAccount, $startDate, $endDate)
    {
        $corp_id = $this->settings['corp_id'];

        $uriSign = "GET:/banking/v3/corporates/$corp_id/accounts/$sourceAccount/statements?EndDate=$endDate&StartDate=$startDate";
        $isoTime = self::generateIsoTime();
        $authSignature = self::generateSign($uriSign, $oauth_token, $this->settings['secret_key'], $isoTime, null);

        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $this->settings['secret_key'];
        $headers['X-BCA-Timestamp'] = $isoTime;
        $headers['X-BCA-Signature'] = $authSignature;

        $request_path = "banking/v3/corporates/$corp_id/accounts/$sourceAccount/statements?EndDate=$endDate&StartDate=$startDate";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $data = array('grant_type' => 'client_credentials');
        $body = Body::form($data);
        $response = Request::get($full_url, $headers, $body);

        return $response;
    }

    public function getAtmLocation(
        $oauth_token,
        $latitude,
        $longitude,
        $count = '10',
        $radius = '20'
    )
    {
        $params = array();
        $params['SearchBy'] = 'Distance';
        $params['Latitude'] = $latitude;
        $params['Longitude'] = $longitude;
        $params['Count'] = $count;
        $params['Radius'] = $radius;
        ksort($params);

        $auth_query_string = self::arrayImplode('=', '&', $params);

        $uriSign = "GET:/general/info-bca/atm?$auth_query_string";
        $isoTime = self::generateIsoTime();
        $authSignature = self::generateSign($uriSign, $oauth_token, $this->settings['secret_key'], $isoTime, null);

        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $this->settings['api_key'];
        $headers['X-BCA-Timestamp'] = $isoTime;
        $headers['X-BCA-Signature'] = $authSignature;

        $request_path = "general/info-bca/atm?SearchBy=Distance&Latitude=$latitude&Longitude=$longitude&Count=$count&Radius=$radius";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $data = array('grant_type' => 'client_credentials');
        $body = Body::form($data);
        $response = Request::get($full_url, $headers, $body);

        return $response;
    }

    public function getForexRate(
        $oauth_token,
        $rateType = 'e-rate',
        $currency = 'USD'
    )
    {
        $params = array();
        $params['RateType'] = strtolower($rateType);
        $params['Currency'] = strtoupper($currency);
        ksort($params);

        $auth_query_string = self::arrayImplode('=', '&', $params);

        $uriSign = "GET:/general/rate/forex?$auth_query_string";
        $isoTime = self::generateIsoTime();
        $authSignature = self::generateSign($uriSign, $oauth_token, $this->settings['secret_key'], $isoTime, null);

        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $this->settings['api_key'];
        $headers['X-BCA-Timestamp'] = $isoTime;
        $headers['X-BCA-Signature'] = $authSignature;

        $request_path = "general/rate/forex?$auth_query_string";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $data = array('grant_type' => 'client_credentials');
        $body = Body::form($data);
        $response = Request::get($full_url, $headers, $body);

        return $response;
    }

    public function fundTransfers(
        $oauth_token,
        $amount,
        $sourceAccountNumber,
        $beneficiaryAccountNumber,
        $referenceID,
        $remark1,
        $remark2,
        $transactionID
    )
    {
        $uriSign = "POST:/banking/corporates/transfers";

        $isoTime = self::generateIsoTime();

        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $this->settings['api_key'];
        $headers['X-BCA-Timestamp'] = $isoTime;

        $request_path = "banking/corporates/transfers";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $bodyData = array();
        $bodyData['Amount'] = $amount;
        $bodyData['BeneficiaryAccountNumber'] = strtolower(str_replace(' ', '', $beneficiaryAccountNumber));
        $bodyData['CorporateID'] = strtolower(str_replace(' ', '', $this->settings['corp_id']));
        $bodyData['CurrencyCode'] = 'idr';
        $bodyData['ReferenceID'] = strtolower(str_replace(' ', '', $referenceID));
        $bodyData['Remark1'] = strtolower(str_replace(' ', '', $remark1));
        $bodyData['Remark2'] = strtolower(str_replace(' ', '', $remark2));
        $bodyData['SourceAccountNumber'] = strtolower(str_replace(' ', '', $sourceAccountNumber));
        $bodyData['TransactionDate'] = $isoTime;
        $bodyData['TransactionID'] = strtolower(str_replace(' ', '', $transactionID));

        // Harus disort agar mudah kalkulasi HMAC
        ksort($bodyData);

        $authSignature = self::generateSign($uriSign, $oauth_token, $this->settings['secret_key'], $isoTime, $bodyData);

        $headers['X-BCA-Signature'] = $authSignature;

        // Supaya jgn strip "ReferenceID" "/" jadi "/\" karena HMAC akan menjadi tidak cocok
        $encoderData = json_encode($bodyData, JSON_UNESCAPED_SLASHES);

        $body = Body::form($encoderData);
        $response = Request::post($full_url, $headers, $body);

        return $response;
    }

    public function getDepositRate($oauth_token)
    {
        $uriSign = "GET:/general/rate/deposit";
        $isoTime = self::generateIsoTime();
        $authSignature = self::generateSign($uriSign, $oauth_token, $this->settings['secret_key'], $isoTime, null);

        $headers = array();
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['Authorization'] = "Bearer $oauth_token";
        $headers['X-BCA-Key'] = $this->settings['api_key'];
        $headers['X-BCA-Timestamp'] = $isoTime;
        $headers['X-BCA-Signature'] = $authSignature;

        $request_path = "general/rate/deposit";
        $domain = $this->ddnDomain();
        $full_url = $domain . $request_path;

        $data = array('grant_type' => 'client_credentials');

        $body = Body::form($data);
        $response = Request::get($full_url, $headers, $body);

        return $response;
    }

    public static function generateSign($url, $auth_token, $secret_key, $isoTime, $bodyToHash = [])
    {
        $hash = hash("sha256", "");
        if (is_array($bodyToHash)) {
            ksort($bodyToHash);
            $encoderData = json_encode($bodyToHash, JSON_UNESCAPED_SLASHES);
            $hash = hash("sha256", $encoderData);
        }
        $stringToSign = $url . ":" . $auth_token . ":" . $hash . ":" . $isoTime;
        $auth_signature = hash_hmac('sha256', $stringToSign, $secret_key, false);

        return $auth_signature;
    }

    public static function setTimeZone($timeZone)
    {
        self::$timezone = $timeZone;
    }

    public static function getTimeZone()
    {
        return self::$timezone;
    }

    public static function setHostName($hostName)
    {
        self::$hostName = $hostName;
    }

    public static function getHostName()
    {
        return self::$hostName;
    }

    public static function getTimeOut()
    {
        return self::$timeOut;
    }

    public static function getCurlOptions()
    {
        return self::$curlOptions;
    }

    public static function setCurlOptions(array $curlOpts = [])
    {
        $data = self::mergeCurlOptions(self::$curlOptions, $curlOpts);
        self::$curlOptions = $data;
    }

    public static function setTimeOut($timeOut)
    {
        self::$timeOut = $timeOut;
        return self::$timeOut;
    }

    public static function setPort($port)
    {
        self::$port = $port;
    }

    public static function getPort()
    {
        return self::$port;
    }

    public static function setScheme($scheme)
    {
        self::$scheme = $scheme;
    }

    public static function getScheme()
    {
        return self::$scheme;
    }

    public static function generateIsoTime()
    {
        $date = Carbon::now(self::getTimeZone());
        date_default_timezone_set(self::getTimeZone());
        $fmt = $date->format('Y-m-d\TH:i:s');
        $ISO8601 = sprintf("$fmt.%s%s", substr(microtime(), 2, 3), date('P'));

        return $ISO8601;
    }

    private static function mergeCurlOptions(&$existing_options, $new_options)
    {
        $existing_options = $new_options + $existing_options;
        return $existing_options;
    }

    private function validateArray($sourceAccountId = [])
    {
        if (!is_array($sourceAccountId)) {
            throw new BcaHttpException('Data harus array.');
        }
        if (empty($sourceAccountId)) {
            throw new BcaHttpException('AccountNumber tidak boleh kosong.');
        } else {
            $max = sizeof($sourceAccountId);
            if ($max > 20) {
                throw new BcaHttpException('Maksimal Account Number ' . 20);
            }
        }

        return true;
    }

    public static function arrayImplode($glue, $separator, $array = [])
    {
        if (!is_array($array)) {
            throw new BcaHttpException('Data harus array.');
        }
        if (empty($array)) {
            throw new BcaHttpException('parameter array tidak boleh kosong.');
        }
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $val = implode(',', $val);
            }
            $string[] = "{$key}{$glue}{$val}";
        }

        return implode($separator, $string);
    }
}
