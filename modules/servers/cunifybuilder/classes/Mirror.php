<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Hosting\Hosting\Code\Classes;

use Symfony\Component\DomCrawler\Crawler;
use Goutte\Client;

/**
 * Description of Mirror
 *
 * @author dedan
 */
class Mirror {

    public $url = '';
    public $root_url = '';
    public $http_root_url = '';
    public $url_content = '';
    public $download_folder = '';
    public $download_folder_extend = '';
    public $download_folder_extend_count = '';
    public $resource_path = '';
    public $loaded_resources = array();
    public $resource_absolute_url = '';

    //put your code here
    public function processMirror($account) {

        $cpanel = new Cpanel();
        $factory = new KazistFactory();

        $url = $this->root_url = $account->domain;

        if ($this->download_folder == '') {
            $this->download_folder = JPATH_ROOT . 'uploads/hosting/backup/' . $account->username . '/';
        }

        $this->http_root_url = 'http://' . $this->root_url;

        $factory->makeDir($this->download_folder);
        $this->getContent('http://' . $url);
    }

    public function getContent($url) {

        $crawler = new Crawler();

        $this->url = $this->relativePath($url);
        $this->url_content = file_get_contents($this->url);
        $this->loaded_resources[] = $this->url;

        $crawler->add($this->url_content);

        $this->processImgContent($crawler);
        $this->processCssContent($crawler);
        $this->processJavascriptContent($crawler);

        $this->url_content = str_replace(rtrim($this->http_root_url, '/') . '/', $this->stepsBackward(), $this->url_content);

        unlink($this->download_folder . $this->download_folder_extend . 'index.html');
        file_put_contents($this->download_folder . $this->download_folder_extend . 'index.html', $this->url_content);

        $this->processLinkContent($crawler);
    }

    public function stepsBackward() {

        $step_backward = '';

        if ($this->download_folder_extend <> '') {

            $rtrim = rtrim($this->download_folder_extend, '/');
            $url_arr = explode('/', $rtrim);

            foreach ($url_arr as $key => $url) {
                $step_backward .= '../';
            }
        }

        return $step_backward;
    }

    public function processImgContent($crawler) {

        $imgs = $this->getSelectorContent($crawler, 'img', 'src');

        foreach ($imgs as $key => $img) {

            $this->relativePath($img[0]);

            if ($this->isDownloadable($this->resource_absolute_url)) {
                $this->getSaveResource();
                $this->replaceResourceName($img[0]);
            }
        }
    }

    public function processLinkContent($crawler) {

        $ignore_arr = array('#', 'javascript:void', 'javascript:void()');
        $links = $this->getSelectorContent($crawler, 'a', 'href');

        $factory = new KazistFactory();

        foreach ($links as $key => $link) {

            if ($link[0] <> '' && !in_array($link[0], $ignore_arr) && substr($link[0], 0, 1) <> '#') {

                $this->relativePath($link[0]);

                if ($this->isDownloadable($this->resource_absolute_url)) {
                    if (!in_array($this->resource_absolute_url, $this->loaded_resources)) {
                        $url_arr = explode($this->root_url, $this->resource_absolute_url);
                        $ltrim = ltrim($url_arr[1], '/');
                        $url_arr_1 = explode('/', $ltrim);

                        $this->download_folder_extend = implode('/', $url_arr_1);

                        $path = $this->download_folder . $this->download_folder_extend;

                        $factory->makeDir($path);

                        $this->getContent($this->resource_absolute_url);
                    }
                }
            }
        }
    }

    public function processCssContent($crawler) {

        $csses = $this->getSelectorContent($crawler, 'link', 'href');

        foreach ($csses as $key => $css) {

            $this->relativePath($css[0]);

            if ($this->isDownloadable($this->resource_absolute_url)) {
                $this->getSaveResource();
                $this->replaceResourceName($css[0]);
            }
        }
    }

    public function processJavascriptContent($crawler) {

        $javascripts = $this->getSelectorContent($crawler, 'script', 'src');

        foreach ($javascripts as $key => $javascript) {

            $this->relativePath($javascript[0]);

            if ($this->isDownloadable($this->resource_absolute_url)) {
                $this->getSaveResource();
                $this->replaceResourceName($javascript[0]);
            }
        }
    }

    public function getSelectorContent($crawler, $selector, $attribute = '') {


        if ($attribute <> '') {
            $content_arr = $crawler
                    ->filter($selector)
                    ->each(function (Crawler $nodeCrawler) use ($attribute) {
                return $nodeCrawler->extract($attribute);
            });
        } else {
            $content_arr = $crawler
                    ->filter($selector)
                    ->each(function (Crawler $nodeCrawler) use ($selector) {

                return '<' . $selector . '>' . $nodeCrawler->html() . '</' . $selector . '>';
            });
        }

        return $content_arr;
    }

    public function isDownloadable($resource_absolute_url) {

        if (strpos($resource_absolute_url, $this->root_url)) {

            $ch = curl_init($resource_absolute_url);

            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($code == 200) {
                return true;
            }
        }

        return false;
    }

    public function getResourcePath($resource_absolute_url) {

        $factory = new KazistFactory();

        $url_arr = explode($this->root_url, $resource_absolute_url);
        $ltrim = ltrim($url_arr[1], '/');
        $url_arr_1 = explode('/', $ltrim);
        $filename = array_pop($url_arr_1);

        $filename = ($filename == '') ? 'index.html' : $filename;

        $path = rtrim($this->download_folder, '/') . '/' . implode('/', $url_arr_1);

        $factory->makeDir($path);

        $this->resource_path = rtrim($path, '/') . '/' . $filename;
    }

    public function getSaveResource() {

        $this->getResourcePath($this->resource_absolute_url);

        $absolute_url_parts = explode('?', $this->resource_path);
        $this->resource_path = $absolute_url_parts[0];

        if (!in_array($this->resource_absolute_url, $this->loaded_resources)) {

            $ch = curl_init($this->resource_absolute_url);
            $fp = fopen($this->resource_path, 'w+');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);

            $this->loaded_resources[] = $this->resource_absolute_url;
        }
    }

    public function replaceResourceName($resource_url) {

        $this->resource_absolute_url;

        $this->url_content = str_replace($resource_url, $this->resource_absolute_url, $this->url_content);
    }

    public function relativePath($resource_url) {

        $factory = new KazistFactory();

        if (substr($resource_url, 0, 4) == 'http') {
            $this->resource_absolute_url = $resource_url;
        } else {
            $this->resource_absolute_url = $this->relativePathHttpAppend($resource_url);
        }

        return $this->resource_absolute_url;
    }

    public function relativePathHttpAppend($resource_url) {

        $url_arr = explode('/', $this->url);
        $url_arr_reverse = array_reverse($url_arr);
        $resource_url_arr = explode('/', $resource_url);
        $resource_url_path = '';

        if ($url_arr_reverse[0] == 'index.php') {
            unset($url_arr_reverse[0]);
            $url_arr_reverse = array_values($url_arr_reverse);
            $url_arr = array_reverse($url_arr_reverse);
        }

        if ($resource_url_arr[0] == '') {
            unset($resource_url_arr[0]);
            $resource_url_arr = array_values($resource_url_arr);
        }

        if ($url_arr_reverse[0] == $resource_url_arr[0]) {
            unset($resource_url_arr[0]);
            $resource_url_arr = $resource_url_path = array_values($resource_url_arr);
            array_pop($resource_url_path);
        }

        $absolute_url_arr = array_merge($url_arr, $resource_url_arr);

        $this->resource_absolute_url = implode('/', $absolute_url_arr);
    }

}
