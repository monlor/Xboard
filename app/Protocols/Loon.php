<?php

namespace App\Protocols;

class Loon
{
    public $flag = 'loon';
    private $servers;
    private $user;

    public function __construct($user, $servers)
    {
        $this->user = $user;
        $this->servers = $servers;
    }

    public function handle()
    {
        $servers = $this->servers;
        $user = $this->user;

        $uri = '';

        foreach ($servers as $item) {
            if ($item['type'] === 'shadowsocks'
                && in_array($item['cipher'], [
                    'aes-128-gcm',
                    'aes-192-gcm',
                    'aes-256-gcm',
                    'chacha20-ietf-poly1305'
                ])
            ) {
                $uri .= self::buildShadowsocks($item['password'], $item);
            }
            if ($item['type'] === 'vmess') {
                $uri .= self::buildVmess($user['uuid'], $item);
            }
            if ($item['type'] === 'trojan') {
                $uri .= self::buildTrojan($user['uuid'], $item);
            }
            if ($item['type'] === 'hysteria') {
                $uri .= self::buildHysteria($user['uuid'], $item, $user);
            }
        }
        return response($uri, 200)
                ->header('Subscription-Userinfo', "upload={$user['u']}; download={$user['d']}; total={$user['transfer_enable']}; expire={$user['expired_at']}");
    }


    public static function buildShadowsocks($password, $server)
    {
        $config = [
            "{$server['name']}=Shadowsocks",
            "{$server['host']}",
            "{$server['port']}",
            "{$server['cipher']}",
            "{$password}",
            'fast-open=false',
            'udp=true'
        ];
        if ($server['obfs'] === 'http') {
            $config[] = "obfs-name=http";
            $config[] = "obfs-host={$server['obfs_settings']['host']}";
        }
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildVmess($uuid, $server)
    {
        $config = [
            "{$server['name']}=vmess",
            "{$server['host']}",
            "{$server['port']}",
            'auto',
            "{$uuid}",
            'fast-open=false',
            'udp=true',
            "alterId=0"
        ];

        if ($server['network'] === 'tcp') {
            array_push($config, 'transport=tcp');
            if ($server['networkSettings']) {
                $tcpSettings = $server['networkSettings'];
                if (isset($tcpSettings['header']['type']) && !empty($tcpSettings['header']['type']))
                    $config = str_replace('transport=tcp', "transport={$tcpSettings['header']['type']}", $config);
                if (isset($tcpSettings['header']['request']['path'][0]) && !empty($tcpSettings['header']['request']['path'][0]))
                    $paths = $tcpSettings['header']['request']['path'];
                    $path = array_rand(array_rand($paths));
                    array_push($config, "path={$path}");
                if (isset($tcpSettings['header']['request']['headers']['Host'][0])){
                    $hosts = $tcpSettings['header']['request']['headers']['Host'];
                    $host = $hosts[array_rand($hosts)];
                    array_push($config, "host={$host}");
                }
            }
        }
        if ($server['tls']) {
            if ($server['network'] === 'tcp')
                array_push($config, 'over-tls=true');
            if ($server['tlsSettings']) {
                $tlsSettings = $server['tlsSettings'];
                if (isset($tlsSettings['allowInsecure']) && !empty($tlsSettings['allowInsecure']))
                    array_push($config, 'skip-cert-verify=' . ($tlsSettings['allowInsecure'] ? 'true' : 'false'));
                if (isset($tlsSettings['serverName']) && !empty($tlsSettings['serverName']))
                    array_push($config, "tls-name={$tlsSettings['serverName']}");
            }
        }
        if ($server['network'] === 'ws') {
            array_push($config, 'transport=ws');
            if ($server['networkSettings']) {
                $wsSettings = $server['networkSettings'];
                if (isset($wsSettings['path']) && !empty($wsSettings['path']))
                    array_push($config, "path={$wsSettings['path']}");
                if (isset($wsSettings['headers']['Host']) && !empty($wsSettings['headers']['Host']))
                    array_push($config, "host={$wsSettings['headers']['Host']}");
            }
        }

        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildTrojan($password, $server)
    {
        $config = [
            "{$server['name']}=trojan",
            "{$server['host']}",
            "{$server['port']}",
            "{$password}",
            $server['server_name'] ? "tls-name={$server['server_name']}" : "",
            'fast-open=false',
            'udp=true'
        ];
        if (!empty($server['allow_insecure'])) {
            array_push($config, $server['allow_insecure'] ? 'skip-cert-verify=true' : 'skip-cert-verify=false');
        }
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    public static function buildHysteria($password, $server, $user)
    {
        if ($server['version'] !== 2){
            return ;
        }
        $config = [
            "{$server['name']}=Hysteria2",
            $server['host'],
            $server['port'],
            $password,
            $server['server_name'] ? "sni={$server['server_name']}" : "(null)"
        ];
        if ($server['insecure'])  $config[] = "skip-cert-verify=true";
        $config[] = "download-bandwidth=" . ($user->speed_limit ? min($server['down_mbps'], $user->speed_limit) : $server['down_mbps']);
        $config[] = "udp=true";
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }
}
