<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Member
 *
 * @author sbc
 */

class CunifyFactory
{
    public function enqueueMessage($dir)
    {
        // enqueue Message
    }

    public function renderString($html, $object_arr)
    {

        $loader = new Twig_Loader_Filesystem(__DIR__ .'/../templates');
        $twig = new Twig_Environment($loader);
        $twig->addExtension(new Twig_Extension_StringLoader());

        $object_arr = json_decode(json_encode($object_arr), true);
        $object_arr = (is_array($object_arr)) ? $object_arr : array();

        $template = $twig->createTemplate($html);

        return $template->render($object_arr);
    }

    public function makeDir($dir)
    {

        if (!file_exists(rtrim($dir, '/'))) {
            $oldmask = umask(0);
            mkdir($dir, 0775, true);
            umask($oldmask);
        }

        $root_path = JPATH_ROOT;
        $new_dir = str_replace($root_path, '', $dir);
        $new_dir_arr = explode('/', $new_dir);

        $path_sum = $root_path;

        foreach ($new_dir_arr as $new_dir_item) {

            $path_sum = rtrim($path_sum, '/') . '/' . $new_dir_item;
            if ($path_sum !== $root_path) {
                if (!file_exists($path_sum . '/index.html')) {
                    file_put_contents($path_sum . '/index.html', '<html></html>');
                }
            }
        }
    }
}
