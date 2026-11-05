<?php

declare(strict_types=1);

namespace App\Helpers;

final class IpAllowlist
{
    public static function enforce(): void
    {
        if (!IP_ALLOWLIST_ENABLED) {
            return;
        }

        $cidrsJson = Settings::get('ip_allowlist_cidrs');
        $cidrs = $cidrsJson ? json_decode($cidrsJson, true) : IP_ALLOWLIST_CIDRS;
        if (!is_array($cidrs) || $cidrs === []) {
            return;
        }

        $ip = client_ip();
        foreach ($cidrs as $cidr) {
            if (is_string($cidr) && self::ipInCidr($ip, $cidr)) {
                return;
            }
        }

        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Access denied: your network is not allowed to use this system.';
        exit;
    }

    public static function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $mask] = explode('/', $cidr, 2);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }
        $mask = (int) $mask;
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false || $mask < 0 || $mask > 32) {
            return false;
        }
        $maskLong = -1 << (32 - $mask);
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
