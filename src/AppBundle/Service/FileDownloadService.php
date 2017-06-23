<?php
declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: seyfer
 * Date: 6/18/17
 */

namespace AppBundle\Service;


use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

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
        $file = fopen($remoteFile, 'rb');

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
     * @param OutputInterface $output
     */
    public static function downloadWithProgress($remoteFile, $localFile, OutputInterface $output)
    {
        $file = fopen($remoteFile, 'rb');

        $size       = self::retrieveRemoteFileSize($remoteFile);
        $bufferSize = 1024 * 8;

        $steps = ceil($size / $bufferSize);

//        exit(dump($size, $bufferSize, $steps));

        $output->writeln('Will download size ' . self::formatBytes($size) .
                         ' with buffer size ' . $bufferSize . ' in steps ' . $steps);

        // create a new progress bar (50 units)
        $progress = new ProgressBar($output, $steps);
        $progress->setFormat('debug');
        $progress->setRedrawFrequency(100);
        // start and displays the progress bar
        $progress->start();

        $newFile = null;
        if ($file) {
            $newFile = fopen($localFile, 'wb');
            if ($newFile) {

                $step = 0;
                while (!feof($file)) {
                    fwrite($newFile, fread($file, $bufferSize), $bufferSize);
                    $step++;
                    $progress->advance();
                }

                // ensure that the progress bar is at 100%
                $progress->finish();
            }
        }

        if ($file) {
            fclose($file);
        }

        if ($newFile) {
            fclose($newFile);
        }
    }

    private static function formatBytes($size, $precision = 2)
    {
        $base     = log($size, 1024);
        $suffixes = ['', 'K', 'M', 'G', 'T'];

        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[(int)floor($base)];
    }

    public static function retrieveRemoteFileSize($url)
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_NOBODY, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $data = curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

        curl_close($ch);

        return $size;
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