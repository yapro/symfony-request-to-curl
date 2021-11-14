<?php

declare(strict_types=1);

namespace YaPro\SymfonyRequestToCurlCommand;

use Symfony\Component\BrowserKit\Request as BrowserKitRequest;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;

/**
 * Преобразовывает текущий/указанный Symfony http-запрос в curl-команду, которую можно выполнить из консоли.
 *
 * Old version https://github.com/yapro/monologext/blob/php5/src/Monolog/Processor/RequestAsCurl.php
 */
final class SymfonyRequestToCurlCommandConverter
{
    private const MAX_DATA_LENGTH = 4000; // count bites
    private HttpFoundationRequest $request;

    public function __construct()
    {
        $this->request = HttpFoundationRequest::createFromGlobals();
    }

    /**
     * Возвращает текущий http-запрос оформленный в curl-команду, которую можно выполнить из консоли.
     *
     * @param HttpFoundationRequest|BrowserKitRequest|null $request
     *
     * @return string
     */
    public function convert($request = null): string
    {
        try {
            if ($request === null) {
                if (PHP_SAPI === 'cli') {
                    return '';
                }
                $request = $this->request;
            }

            if (
                !$request instanceof HttpFoundationRequest &&
                !$request instanceof BrowserKitRequest
            ) {
                throw new \UnexpectedValueException('Unsupported request type');
            }

            $url = '';
            $content = '';
            $headers = [];

            if ($request instanceof HttpFoundationRequest) {
                $url = $request->getSchemeAndHttpHost();
                // @todo check it: $request->getUri() return wrong result because $this->request->PathInfo() returning a slash on end of path
                if ($request->getPathInfo()) {
                    $url .= $request->getPathInfo();
                }
                if ($request->getQueryString()) {
                    $url .= '?' . $request->getQueryString();
                }
                $headers = $this->getHeadersFromHeaderBug($request->headers);
                if (count($headers) < 1) {
                    // try to get headers from server variables:
                    $headers = $request->server->getHeaders();
                }
                $content = $request->getContent();
                if ($content === '') {
                    $requestParameters = $request->request->all();
                    if ($requestParameters) {
                        $content = json_encode($requestParameters, JSON_THROW_ON_ERROR);
                    }
                }
                /*
                $_GET = $request->query->all();
                $_POST = $request->request->all();
                $_SERVER = $request->server->all();
                $_COOKIE = $request->cookies->all();
                */
            }

            if ($request instanceof BrowserKitRequest) {
                $url = $request->getUri();
                $headers = $request->getServer();
                $cookies = [];
                foreach ($request->getCookies() as $cookieName => $cookieValue) {
                    $cookies[] = $cookieName . '=' . $cookieValue;
                    // for repeat the request with a real web-server
                    if ($cookieName === 'MOCKSESSID') {
                        $cookies[] = 'PHPSESSID=' . $cookieValue;
                    }
                }
                if (count($cookies)) {
                    $headers['Cookie'] = trim(implode('; ', $cookies));
                }
                if (count($request->getParameters())) {
                    $requestParameters = $request->getParameters();
                    if (array_key_exists('json', $requestParameters)) {
                        $content = json_encode($requestParameters['json'], JSON_THROW_ON_ERROR);
                    }
                }
            }

            $parts = ['command' => 'curl -i -L --insecure'];
            $parts['url'] = "'" . $url . "'";
            $parts['headers'] = $this->getHeaders($headers);

            if (in_array($request->getMethod(), ['GET', 'HEAD'], true) === false) {
                $parts['method'] = '-X ' . $request->getMethod();
                if ($content !== '') {
                    if (strlen($content) > self::MAX_DATA_LENGTH) {
                        $content = 'Sorry, data length was very big';
                    }
                    $parts['data'] = '--data \'' . $content . '\'';
                }
            }

            return implode(' ', $parts);
        } catch (\Exception $e) {
            trigger_error(
                $e->getMessage() . ' ' .
                $e->getFile() . ':' . $e->getLine() . ' ' .
                str_replace("\n", '\n', $e->getTraceAsString())
            );

            return '';
        }
    }

    private function getHeadersFromHeaderBug(HeaderBag $headerBug): array
    {
        $result = [];
        foreach ($headerBug->all() as $headerName => $values) {
            $result[$headerName] = implode(';', $values);
        }

        return $result;
    }

    /**
     * maybe the following code is much better - \Symfony\Component\BrowserKit\HttpBrowser::getHeaders().
     *
     * @param array $headersOrServerVariables
     *
     * @return string
     */
    private function getHeaders(array $headersOrServerVariables): string
    {
        $result = [];
        foreach ($headersOrServerVariables as $key => $value) {
            // Section 3.1 of RFC 822 [9] : Field names are case-insensitive https://stackoverflow.com/questions/7718476
            $key = mb_strtolower($key);

            // delete the prefix that added by php for headers in $_SERVER variable:
            if (mb_strpos($key, 'http_') === 0) {
                $key = mb_substr($key, 5);
            }

            // deleting blanks
            $key = str_replace(' ', '-', str_replace('_', ' ', $key));

            // deleting not a header key
            //if (count(explode('-', $key)) < 2) {
            //    continue;
            //}

            $result[] = '-H \'' . $key . ': ' . $value . '\'';
        }

        return implode(' ', $result);
    }
}
