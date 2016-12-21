<?php

namespace Tollwerk\TwAntibot\Utility;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Joschi Kuphal <joschi@tollwerk.de>, tollwerk GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * BotSmasher client
 *
 */
class BotSmasherClient
{
    /**
     * API key
     *
     * @var string
     */
    protected $_apiKey = null;
    /**
     * API URL
     *
     * @var string
     */
    protected $_apiUrl = null;
    /**
     * Invalid IP address
     *
     * @var \int
     */
    const IP_INVALID = 1;
    /**
     * Private IP address
     *
     * @var \int
     */
    const IP_PRIVATE = 2;
    /**
     * Invalid email address
     *
     * @var \int
     */
    const EMAIL_INVALID = 3;
    /**
     * Check action
     *
     * @var \string
     */
    const ACTION_CHECK = 'check';
    /**
     * Invalid response status (check was not successful)
     *
     * @var \int
     */
    const STATUS_INVALID = 0;
    /**
     * Check was negative (none of the checks were positive)
     *
     * @var \int
     */
    const STATUS_VALID = 1;
    /**
     * The IP address is known as a spammer / fraudulent
     *
     * @var \int
     */
    const STATUS_IP = 2;
    /**
     * The email address is known as a spammer / fraudulent
     *
     * @var \int
     */
    const STATUS_EMAIL = 4;
    /**
     * The name is known as a spammer / fraudulent
     *
     * @var \int
     */
    const STATUS_NAME = 8;
    /**
     * Check to status mapping
     *
     * @var \array
     */
    protected static $_checks = array(
        'ip' => self::STATUS_IP,
        'email' => self::STATUS_EMAIL,
        'name' => self::STATUS_NAME
    );

    /**
     * Constructor
     *
     * @param \array $config Configuration
     * @throws \Tollwerk\TwAntibot\Utility\BotSmasher\Exception        If there is no API key or URL provided
     */
    public function __construct(array $config)
    {
        $this->_apiKey = empty($config['apiKey']) ? '' : trim($config['apiKey']);
        $this->_apiUrl = empty($config['apiKey']) ? '' : trim(trim($config['apiUrl']), '/').'/';

        if (!strlen($this->_apiUrl) || !filter_var($this->_apiUrl, FILTER_VALIDATE_URL)) {
            throw new BotSmasher\Exception(sprintf('Invalid BotSmasher API URL "%s"', $this->_apiUrl));
        }

        if (!strlen($this->_apiKey) || (strlen($this->_apiKey) != 64)) {
            throw new BotSmasher\Exception(sprintf('Invalid BotSmasher API key "%s"', $this->_apiKey));
        }
    }

    /**
     * Check the BotSmasher API
     *
     * @param \string $ip IP address
     * @param \string $email Email address
     * @param \string $name Name
     * @param \boolean $debug Debug cUrl communication
     * @param \int BotSmasher status
     * @throws \Tollwerk\TwAntibot\Utility\BotSmasher\Exception        If an invalid IP address is provided
     */
    public function check($ip = null, $email = null, $name = null, $debug = false)
    {
        $status = self::STATUS_INVALID;
        $checks = array();
        $errors = new BotSmasher\Exception();

        // IP address check
        if (strlen(trim($ip))) {

            // If the IP address is invalid
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                $errors->addMessage(sprintf('Invalid IP address "%s"', $ip), self::IP_INVALID);

                // Else if the IP address is private
            } elseif (filter_var($ip, FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            ) {
                $errors->addMessage(sprintf('Private IP address "%s"', $ip), self::IP_PRIVATE);

                // Else
            } else {
                $checks['ip'] = trim($ip);
            }
        }

        // Email address check
        if (strlen(trim($email))) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors->addMessage(sprintf('Invalid email address "%s"', $email), self::EMAIL_INVALID);
            } else {
                $checks['email'] = trim($email);
            }
        }

        // Name check
        if (strlen(trim($name))) {
            $checks['name'] = trim($name);
        }

        // If no checks are requested: Error
        if (!count($checks)) {
            if (count($errors)) {
                throw $errors;
            } else {
                return $status;
            }
        }

        $checks['key'] = $this->_apiKey;
        $checks['action'] = self::ACTION_CHECK;
        if ($debug) {
            \ChromePhp::log($checks);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_apiUrl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $checks);

        // Execute post and get results
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

// 		$result = '{"response":{"summary":{"badguys":"false","requesttype":"check","code":"success","description":"Your request was successful - "},"request":{"email":{"submitted":"joschi@tollwerk.de","flaggedbyyou":"false","found":"false","count":"0"}}}}';
// 		$info = array(
// 			'url' => 'https://www.botsmasher.com/api/',
// 			'content_type' => 'text/html; charset=utf-8',
// 			'http_code' => 200,
// 			'header_size' => 261,
// 			'request_size' => 191,
// 			'filetime' => -1,
// 			'ssl_verify_result' => 10,
// 			'redirect_count' => 0,
// 			'total_time' => 1.4916369999999999,
// 			'namelookup_time' => 0.0038340000000000002,
// 			'connect_time' => 0.122919,
// 			'pretransfer_time' => 0.37947500000000001,
// 			'size_upload' => 414,
// 			'size_download' => 236,
// 			'speed_download' => 158,
// 			'speed_upload' => 277,
// 			'download_content_length' => 236,
// 			'upload_content_length' => 414,
// 			'starttransfer_time' => 0.49339500000000003,
// 			'redirect_time' => 0,
// 			'redirect_url' => '',
// 			'primary_ip' => '65.181.127.31',
// 			'certinfo' => array(),
// 			'primary_port' => 443,
// 			'local_ip' => '192.168.123.18',
// 			'local_port' => 34043
// 		);

// 		$result = '{"response":{"summary":{"badguys":"true","requesttype":"check","code":"success","description":"Your request was successful - "},"request":{"email":{"submitted":"test@test.com","flaggedbyyou":"false","found":"true","count":"6","lastseen":"2015-08-08 10:10:04"}}}}';
// 		$info = array(
// 			'url' => 'https://www.botsmasher.com/api/',
// 			'content_type' => 'text/html; charset=utf-8',
// 			'http_code' => 200,
// 			'header_size' => 261,
// 			'request_size' => 191,
// 			'filetime' => -1,
// 			'ssl_verify_result' => 10,
// 			'redirect_count' => 0,
// 			'total_time' => 0.70769400000000005,
// 			'namelookup_time' => 3.4999999999999997E-5,
// 			'connect_time' => 0.113187,
// 			'pretransfer_time' => 0.36741499999999999,
// 			'size_upload' => 409,
// 			'size_download' => 262,
// 			'speed_download' => 370,
// 			'speed_upload' => 577,
// 			'download_content_length' => 262,
// 			'upload_content_length' => 409,
// 			'starttransfer_time' => 0.47937000000000002,
// 			'redirect_time' => 0,
// 			'redirect_url' => '',
// 			'primary_ip' => '65.181.127.31',
// 			'certinfo' => array(),
// 			'primary_port' => 443,
// 			'local_ip' => '192.168.123.18',
// 			'local_port' => 39524
// 		);

        if ($debug) {
            echo curl_error($ch).PHP_EOL;
            var_export($info);
            print_r($result);
        }

        if (($info['http_code'] == 200) && strlen($result)) {
            $response = @json_decode($result);
            if (is_object($response) && isset($response->response) && is_object($response->response)) {
                $response = $response->response;
                $summary = (isset($response->summary) && is_object($response->summary)) ? $response->summary : null;
                $request = (isset($response->request) && is_object($response->request)) ? $response->request : null;
                if ($summary && $request) {
                    if (!empty($summary->code) && (strtolower($summary->code) == 'success')) {
                        if (!empty($summary->badguys) && (strtolower($summary->badguys) == 'true')) {

                            // Run through all check results
                            foreach ($request as $check => $checkResult) {
                                if (is_object($checkResult) && isset($checkResult->found) && (strtolower($checkResult->found) == 'true')) {
                                    $status |= self::$_checks[$check];
                                }
                            }

                        } else {
                            $status = self::STATUS_VALID;
                        }
                    }
                }
            }
        }

        return $status;
    }
}
