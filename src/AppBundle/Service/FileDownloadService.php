<?php
declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: seyfer
 * Date: 6/18/17
 */

namespace AppBundle\Service;


class FileDownloadService
{
    /**
     *  HTTP GET request with curl that writes the curl result into a local file.
     * @access  private
     * @param   string $remoteFile String, containing the remote file URL to curl.
     * @param   string $localFile String, containing the path to the file to save
     *                                  the curl result in to.
     * @return  void
     */
    public static function downloadWithCurl($remoteFile, $localFile)
    {
        $ch = curl_init($remoteFile);

//            curl_setopt($ch, CURLOPT_USERAGENT, $this->CURL_UA);
//            curl_setopt($ch, CURLOPT_REFERER, $this->YT_BASE_URL);

        $fp = fopen($localFile, 'w');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
    }

    /**
     * @param $remoteFile
     * @param $localFile
     */
    public static function downloadWithFopen($remoteFile, $localFile)
    {
        $file    = fopen($remoteFile, 'rb');
        $newFile = null;
        if ($file) {
            $newFile = fopen($localFile, 'wb');
            if ($newFile) {
                while (!feof($file)) {
                    fwrite($newFile, fread($file, 1024 * 8), 1024 * 8);
                }
            }
        }

        if ($file) {
            fclose($file);
        }

        if ($newFile) {
            fclose($newFile);
        }
    }

    /**
     * @param $remoteFile
     * @param $localFile
     */
    public static function downloadWithFileput($remoteFile, $localFile)
    {
        file_put_contents($localFile, fopen($remoteFile, 'r'));
    }
}