<?php

declare(strict_types=1);

namespace App\Support;

final class TableQrUrl
{
    public static function forToken(?string $token): ?string
    {
        $baseUrl = self::publicBaseUrl();
        if ($baseUrl === null || $token === null || $token === '') {
            return null;
        }

        return $baseUrl.route('customer.order.show', ['tableToken' => $token], false);
    }

    public static function isConfigured(): bool
    {
        return self::publicBaseUrl() !== null;
    }

    public static function configurationMessage(): string
    {
        return 'Set APP_URL to your public website URL, or your computer private LAN IPv4 for same-Wi-Fi QR testing. Do not use localhost or 127.0.0.1.';
    }

    private static function publicBaseUrl(): ?string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        if (self::isReachableBaseUrl($baseUrl)) {
            return $baseUrl;
        }

        $request = request();
        $host = $request->getHost();
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            || ! self::isPrivateIpv4($host)) {
            return null;
        }

        return $request->getScheme().'://'.$host.($request->getPort() === 80 || $request->getPort() === 443
            ? ''
            : ':'.$request->getPort());
    }

    private static function isReachableBaseUrl(string $baseUrl): bool
    {
        $parts = parse_url($baseUrl);
        if (! is_array($parts)) {
            return false;
        }

        $host = rtrim(strtolower((string) ($parts['host'] ?? '')), '.');
        $ip = filter_var($host, FILTER_VALIDATE_IP);
        $isPublicIp = $ip !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        $isLanIpv4 = $ip !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && self::isPrivateIpv4($host);

        return in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && $host !== ''
            && $host !== 'localhost'
            && ! str_ends_with($host, '.localhost')
            && ! ($ip !== false && ! $isPublicIp && ! $isLanIpv4);
    }

    private static function isPrivateIpv4(string $ip): bool
    {
        $address = ip2long($ip);
        if ($address === false) {
            return false;
        }

        return ($address & 0xff000000) === 0x0a000000
            || ($address & 0xfff00000) === 0xac100000
            || ($address & 0xffff0000) === 0xc0a80000;
    }
}
