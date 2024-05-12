<?php

namespace Angle\AuditBundle\Utility;

abstract class UbuntuUtility
{

    // TODO: Check the current OS version (lsb_release)
    public function isSupportedUbuntu(): bool
    {
        // check that the distro is Ubuntu

        // check that the version is within 18.04 - 22.04

        return false;
    }

    // TODO: SSH Authorized Keys processing
    public function getSSHAuthorizedKeys(): ?array
    {
        // look into /home/* to find the correct .ssh/authorized_keys folder

        // return an array with: [$user => $authorized_keys_content]

        return null;
    }

    // TODO: User access log processing (/etc/auth ??)
    public function getAuthLog(): ?array
    {
        // look in to the /etc/auth.log (or /var/auth.log ? )

        // filter by dates

        // return an array with one element per matched row

        return null;
    }
}