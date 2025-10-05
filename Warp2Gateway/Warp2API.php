<?php

class Warp2API
{
    const EXCEPTIONS = [
        1001 => "Could not get a valid token.",
        1002 => "HTTP error. Status code: ",
        1003 => "Invalid configuration.",
        1004 => "Gateway host not set.",
        1005 => "Gateway host not reachable.",
        1006 => "User, password, and serial number not properly set.",
        1007 => "CURL error: ",
        1008 => "API response error.",
        1009 => "Unable to retrieve token.",
        1010 => "Invalid token."
    ];

    public function init($config) {
        $this->validateConfig($config);
        return $config;
    }

    protected function validateConfig($config)
    {
        if (! is_array($config)) {
            throw new Exception(self::EXCEPTIONS[1003], 1003);
        }
        if (empty($config['host'])) {
            throw new Exception(self::EXCEPTIONS[1004], 1004);
        }
        
        if (! $this->isHost($config['host'])) {
            throw new Exception(self::EXCEPTIONS[1005], 1005);
        }
    }
    
    function isHost($host) {
        // Check if the host is an IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $url = "http://" . $host;
        } else {
            // If the host doesn't start with 'http://', add 'http://'
            if (!preg_match('#^http?://#', $host)) {
                $url = "http://" . $host;
            } else {
                $url = $host;
            }
        }
    
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);    // Disable SSL certificate verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);    // Disable host verification
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);        // Timeout for the connection attempt
        curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $statusCode > 0;
    }
    
    public function apiRequest($config, $api_command, $method = 'GET', $payload = null)
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($method == 'PUT') {
            if (empty($payload)) {
                $payload = '{}';
            } else {
                $payload = json_encode($payload);
            }
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_URL, rtrim($config['host'], '/'). '/' . ltrim($api_command, '/'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        if (defined('CURL_REDIR_POST_ALL')) {
            curl_setopt($ch, CURLOPT_POSTREDIR, CURL_REDIR_POST_ALL);
        }
        
        if ($method == "PUT" && ! empty($payload)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        
        if (!empty($config['user']) && !empty($config['password'])) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC | CURLAUTH_DIGEST);
            curl_setopt($ch, CURLOPT_USERPWD, $config['user'] . ':' . $config['password']);
        }
        
        $response = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $curl_errno = curl_errno($ch);
        if ($curl_errno) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception(self::EXCEPTIONS[1007] . $curl_errno . ", " . $error, 1007);
        }
        curl_close($ch);
        
        if ($httpStatus != 200) {
            throw new Exception(self::EXCEPTIONS[1002] . $httpStatus, 1002);
        }

        return $response;
    }
}
