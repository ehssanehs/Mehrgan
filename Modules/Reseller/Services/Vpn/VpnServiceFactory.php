<?php

namespace Modules\Reseller\Services\Vpn;

use Modules\Reseller\Models\VpnServer;

class VpnServiceFactory
{
    public static function create(VpnServer $server): VpnServiceInterface
    {
        return match ($server->type) {
            'marzban' => new MarzbanService(),
            'sanaei' => new SanaeiService(),
            'pasarguard' => new PasarGuardService(),
            default => throw new \Exception("Unsupported server type: {$server->type}"),
        };
    }
}
