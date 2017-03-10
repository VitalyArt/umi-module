<?php

/**
 * Class RequestProxy
 * @package RetailCrm\Component
 */
class RCrmProxy
{
    private $api;

    /**
     * Методы API которые не надо логировать
     * @var array
     */
    private $ignoreMethods = array(
        'customersList',
        'customersGet',
        'ordersList',
        'ordersGet',
    );

    /**
     * Методы API трассировку которых надо логировать
     * @var array
     */
    private $traceMethods = array(
        'customersCreate',
        'customersEdit',
        'ordersCreate',
        'ordersEdit',
    );

    /**
     * RCrmProxy constructor.
     * @param string $apiUrl
     * @param string $apiKey
     */
    public function __construct($apiUrl, $apiKey)
    {
        $this->api = new RCrmApiClient($apiUrl, $apiKey);
    }

    /**
     * @param $method
     * @param $arguments
     * @return bool|RCrmApiResponse
     */
    public function __call($method, $arguments)
    {
        try {
            $response = call_user_func_array(array($this->api, $method), $arguments);

            if (!in_array($method, $this->ignoreMethods)) {
                $this->writeLog($method, $response);
            }

            return $response;
        } catch (RCrmCurlException $e) {
            $this->writeLog($method, $e->getMessage());
            return false;
        } catch (RCrmJsonException $e) {
            $this->writeLog($method, $e->getMessage());
            return false;
        }
    }

    /**
     * @param string $method
     * @param mixed $data
     * @return bool
     */
    private function writeLog($method, $data)
    {
        $path = realpath(__DIR__ . '/../../logs/');
        $file = $path . '/' . $method . '.log';

        if (!file_exists($path)) {
            mkdir($path);
        }

        if (file_exists($file)) {
            if (filesize($file) > 1024 * 1024 * 10) {
                unlink($file);
            }
        }

        $logArray = array(
            'time' => date('Y-m-d H:i:s'),
            'data' => $data
        );

        if (in_array($method, $this->traceMethods)) {
            $traces = debug_backtrace();

            foreach ($traces as $i => $trace) {
                if ($i == 0) {
                    unset($traces[$i]);
                    continue;
                }

                unset($traces[$i]['args']);
                unset($traces[$i]['object']);
            }

            $logArray['trace'] = array_reverse($traces);
        }

        return file_put_contents($file, print_r($logArray, true) . "\n\n", FILE_APPEND);
    }
}