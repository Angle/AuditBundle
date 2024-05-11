<?php

namespace Angle\AuditBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class AngleAuditBundle extends Bundle
{
    public function getPath(): string
    {
        return __DIR__;
    }
}