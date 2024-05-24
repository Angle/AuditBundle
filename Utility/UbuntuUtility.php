<?php

namespace Angle\AuditBundle\Utility;

use DirectoryIterator;

abstract class UbuntuUtility
{
    public static function isSupportedUbuntu(): bool
    {
        // check that the distro is Ubuntu
        $os = trim(@shell_exec('lsb_release -si 2>/dev/null'));
        $ver = trim(@shell_exec('lsb_release -sr 2>/dev/null'));

        if ($os !== 'Ubuntu') {
            return false;
        }

        // check that the version is within 18.04 - 22.04
        if (!in_array($ver, ['18.04', '20.04', '22.04'])) {
            return false;
        }

        return true;
    }


    /**
     * Return an array with: [$user => $authorized_keys_content[]]
     * @return array|null
     */
    public static function getSSHAuthorizedKeys(): ?array
    {
        if (!self::isSupportedUbuntu()) {
            return null;
        }

        $keys = [];

        $dir = new DirectoryIterator('/home/');
        foreach ($dir as $fileinfo) {
            /** @var \DirectoryIterator $fileinfo */
            if (!$fileinfo->isDot()) {
                if ($fileinfo->isDir() && $fileinfo->isReadable()) {
                    // we are now in the $user home directory!
                    $user = $fileinfo->getBasename();
                    $lines = [];

                    // we will check if the SSH AuthorizedKeys file exists
                    $authorizedKeysFile = $fileinfo->getRealPath() . '/.ssh/authorized_keys';
                    if (file_exists($authorizedKeysFile)) {

                        // file found, read all contents into an array
                        // Open the file
                        $fp = @fopen($authorizedKeysFile, 'r');

                        // Add each line to an array
                        if ($fp) {
                            $lines = explode("\n", fread($fp, filesize($authorizedKeysFile)));
                            fclose($fp);
                        }
                    }

                    $keys[$user] = $lines;
                }
            }
        }

        return $keys;
    }

    // TODO: User access log processing (/etc/auth ??)
    public static function getAuthLog(): ?array
    {
        // look in to the /etc/auth.log (or /var/auth.log ? )

        // filter by dates

        // return an array with one element per matched row

        return null;
    }
}