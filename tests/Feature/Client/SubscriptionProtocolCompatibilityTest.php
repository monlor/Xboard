<?php

namespace Tests\Feature\Client;

use App\Models\Server;
use App\Protocols\Clash;
use App\Protocols\ClashMeta;
use App\Protocols\Loon;
use App\Protocols\Shadowrocket;
use App\Protocols\SingBox;
use App\Protocols\Stash;
use App\Protocols\Surge;
use App\Support\AbstractProtocol;
use Tests\TestCase;

class SubscriptionProtocolCompatibilityTest extends TestCase
{
    public function test_clash_and_mihomo_flags_route_to_the_correct_protocol_handler(): void
    {
        $manager = app('protocols.manager');

        $this->assertSame(Clash::class, $manager->matchProtocolClassName('clash'));
        $this->assertSame(ClashMeta::class, $manager->matchProtocolClassName('mihomo/1.19.9'));
        $this->assertSame(ClashMeta::class, $manager->matchProtocolClassName('clash-meta/1.19.9'));
        $this->assertSame(ClashMeta::class, $manager->matchProtocolClassName('clash-verge-rev/1.6.0'));
    }

    public function test_base_version_rules_filter_missing_and_low_version_clients(): void
    {
        $anytls = $this->server(Server::TYPE_ANYTLS, 'AnyTLS');
        $naive = $this->server(Server::TYPE_NAIVE, 'Naive');

        $this->assertSame([], $this->filteredNames(Shadowrocket::class, 'shadowrocket', null, [$anytls]));
        $this->assertSame([], $this->filteredNames(Shadowrocket::class, 'shadowrocket', '2591', [$anytls]));
        $this->assertSame(['AnyTLS'], $this->filteredNames(Shadowrocket::class, 'shadowrocket', '2592', [$anytls]));

        $this->assertSame([], $this->filteredNames(Stash::class, 'stash', '3.2.9', [$anytls]));
        $this->assertSame(['AnyTLS'], $this->filteredNames(Stash::class, 'stash', '3.3.0', [$anytls]));

        $this->assertSame([], $this->filteredNames(SingBox::class, 'sing-box', '1.11.0', [$anytls]));
        $this->assertSame(['AnyTLS'], $this->filteredNames(SingBox::class, 'sing-box', '1.12.0', [$anytls]));

        $this->assertSame([], $this->filteredNames(SingBox::class, 'sing-box', '1.7.9', [$naive]));
        $this->assertSame(['Naive'], $this->filteredNames(SingBox::class, 'sing-box', '1.8.0', [$naive]));
    }

    public function test_loon_filters_hysteria_v1_and_gates_anytls_by_build(): void
    {
        $servers = [
            $this->server(Server::TYPE_HYSTERIA, 'Hy1', ['version' => 1]),
            $this->server(Server::TYPE_HYSTERIA, 'Hy2', ['version' => 2]),
            $this->server(Server::TYPE_ANYTLS, 'AnyTLS'),
        ];

        $this->assertSame(['Hy2'], $this->filteredNames(Loon::class, 'loon', '944', $servers));
        $this->assertSame(['Hy2', 'AnyTLS'], $this->filteredNames(Loon::class, 'loon', '945', $servers));
    }

    public function test_surge_builders_emit_hysteria2_obfs_port_hopping_and_tuic(): void
    {
        $hysteria = $this->server(Server::TYPE_HYSTERIA, 'Hy2', [
            'version' => 2,
            'obfs' => [
                'open' => true,
                'type' => 'salamander',
                'password' => 'obfs-pass',
            ],
            'hop_interval' => 45,
        ], ['ports' => '20000-20100']);

        $tuic = $this->server(Server::TYPE_TUIC, 'TUIC', [
            'hop_interval' => 30,
        ], ['ports' => '30000-30100']);
        $tuic4 = $this->server(Server::TYPE_TUIC, 'TUIC4', [
            'version' => 4,
        ]);

        $hysteriaLine = Surge::buildHysteria('node-pass', $hysteria);
        $tuicLine = Surge::buildTuic('node-pass', $tuic);
        $tuic4Line = Surge::buildTuic('node-pass', $tuic4);

        $this->assertStringContainsString('Hy2 = hysteria2', $hysteriaLine);
        $this->assertStringContainsString('password=node-pass', $hysteriaLine);
        $this->assertStringContainsString('salamander-password=obfs-pass', $hysteriaLine);
        $this->assertStringContainsString('port-hopping=20000-20100', $hysteriaLine);
        $this->assertStringContainsString('port-hopping-interval=45', $hysteriaLine);

        $this->assertStringContainsString('TUIC = tuic', $tuicLine);
        $this->assertStringContainsString('uuid=node-pass', $tuicLine);
        $this->assertStringContainsString('password=node-pass', $tuicLine);
        $this->assertStringContainsString('version=5', $tuicLine);
        $this->assertStringNotContainsString('token=node-pass', $tuicLine);
        $this->assertStringContainsString('alpn=h3', $tuicLine);
        $this->assertStringContainsString('port-hopping=30000-30100', $tuicLine);

        $this->assertStringContainsString('TUIC4 = tuic', $tuic4Line);
        $this->assertStringContainsString('token=node-pass', $tuic4Line);
        $this->assertStringNotContainsString('uuid=node-pass', $tuic4Line);
    }

    public function test_clash_meta_builders_emit_modern_protocol_fields(): void
    {
        $hy2 = ClashMeta::buildHysteria('node-pass', $this->server(Server::TYPE_HYSTERIA, 'Hy2'), $this->user());
        $tuic4 = ClashMeta::buildTuic('node-pass', $this->server(Server::TYPE_TUIC, 'TUIC4', ['version' => 4]));
        $tuic5 = ClashMeta::buildTuic('node-pass', $this->server(Server::TYPE_TUIC, 'TUIC5', ['version' => 5]));
        $anytls = ClashMeta::buildAnyTLS('node-pass', $this->server(Server::TYPE_ANYTLS, 'AnyTLS'));

        $this->assertSame('hysteria2', $hy2['type']);
        $this->assertSame('salamander', $hy2['obfs']);
        $this->assertSame('obfs-pass', $hy2['obfs-password']);

        $this->assertSame('node-pass', $tuic4['token']);
        $this->assertArrayNotHasKey('uuid', $tuic4);
        $this->assertSame('node-pass', $tuic5['uuid']);
        $this->assertSame('node-pass', $tuic5['password']);

        $this->assertSame('anytls', $anytls['type']);
        $this->assertSame('node-pass', $anytls['password']);
        $this->assertSame('tls.example.com', $anytls['sni']);
        $this->assertTrue($anytls['skip-cert-verify']);
    }

    public function test_tuic_builders_handle_string_versions_consistently(): void
    {
        $tuic4 = $this->server(Server::TYPE_TUIC, 'TUIC4', ['version' => '4']);

        $surgeLine = Surge::buildTuic('node-pass', $tuic4);
        $this->assertStringContainsString('token=node-pass', $surgeLine);
        $this->assertStringNotContainsString('uuid=node-pass', $surgeLine);

        $shadowrocketParams = $this->queryParams(Shadowrocket::buildTuic('node-pass', $tuic4));
        $this->assertSame('h3', $shadowrocketParams['alpn']);
        $this->assertSame('node-pass', $shadowrocketParams['token']);
        $this->assertArrayNotHasKey('uuid', $shadowrocketParams);

        $clashMeta = ClashMeta::buildTuic('node-pass', $tuic4);
        $this->assertSame('node-pass', $clashMeta['token']);
        $this->assertArrayNotHasKey('uuid', $clashMeta);

        $stash = Stash::buildTuic('node-pass', $tuic4);
        $this->assertSame(4, $stash['version']);
        $this->assertSame('node-pass', $stash['token']);
        $this->assertArrayNotHasKey('uuid', $stash);

        $protocol = new SingBox($this->user(), [], 'sing-box', '1.5.0', 'sing-box/1.5.0');
        $method = new \ReflectionMethod(SingBox::class, 'buildTuic');
        $method->setAccessible(true);
        $singBox = $method->invoke($protocol, 'node-pass', $tuic4);

        $this->assertSame('node-pass', $singBox['token']);
        $this->assertArrayNotHasKey('uuid', $singBox);
    }

    public function test_sing_box_builds_naive_outbound(): void
    {
        $protocol = new SingBox($this->user(), [], 'sing-box', '1.8.0', 'sing-box/1.8.0');
        $method = new \ReflectionMethod(SingBox::class, 'buildNaive');
        $method->setAccessible(true);

        $outbound = $method->invoke($protocol, 'node-pass', $this->server(Server::TYPE_NAIVE, 'Naive'));

        $this->assertSame('naive', $outbound['type']);
        $this->assertSame('Naive', $outbound['tag']);
        $this->assertSame('node-pass', $outbound['username']);
        $this->assertSame('node-pass', $outbound['password']);
        $this->assertTrue($outbound['tls']['enabled']);
        $this->assertSame('tls.example.com', $outbound['tls']['server_name']);
    }

    private function filteredNames(string $protocolClass, ?string $clientName, ?string $clientVersion, array $servers): array
    {
        $protocol = new $protocolClass(
            $this->user(),
            $servers,
            $clientName,
            $clientVersion,
            $clientName && $clientVersion ? "{$clientName}/{$clientVersion}" : $clientName
        );

        $property = new \ReflectionProperty(AbstractProtocol::class, 'servers');
        $property->setAccessible(true);

        return array_values(array_column($property->getValue($protocol), 'name'));
    }

    private function queryParams(string $uri): array
    {
        parse_str((string) parse_url(trim($uri), PHP_URL_QUERY), $params);
        return $params;
    }

    private function user(): array
    {
        return [
            'u' => 0,
            'd' => 0,
            'transfer_enable' => 1024 * 1024 * 1024,
            'expired_at' => null,
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'token' => 'test-token',
        ];
    }

    private function server(string $type, string $name, array $settings = [], array $overrides = []): array
    {
        $server = [
            'id' => crc32($name),
            'name' => $name,
            'type' => $type,
            'host' => 'example.com',
            'port' => 443,
            'server_port' => 443,
            'password' => 'node-pass',
            'protocol_settings' => array_replace_recursive($this->defaultProtocolSettings($type), $settings),
            'tags' => [],
        ];

        return array_replace_recursive($server, $overrides);
    }

    private function defaultProtocolSettings(string $type): array
    {
        return match ($type) {
            Server::TYPE_SHADOWSOCKS => [
                'cipher' => 'aes-128-gcm',
            ],
            Server::TYPE_VMESS => [
                'tls' => 1,
                'tls_settings' => $this->tlsSettings(),
                'network' => 'ws',
                'network_settings' => ['path' => '/', 'headers' => ['Host' => 'ws.example.com']],
            ],
            Server::TYPE_VLESS => [
                'tls' => 1,
                'tls_settings' => $this->tlsSettings(),
                'network' => 'ws',
                'network_settings' => ['path' => '/', 'headers' => ['Host' => 'ws.example.com']],
                'flow' => null,
            ],
            Server::TYPE_TROJAN => [
                'tls' => 1,
                'tls_settings' => $this->tlsSettings(),
                'network' => 'tcp',
                'network_settings' => [],
            ],
            Server::TYPE_HYSTERIA => [
                'version' => 2,
                'bandwidth' => ['up' => 100, 'down' => 100],
                'obfs' => [
                    'open' => true,
                    'type' => 'salamander',
                    'password' => 'obfs-pass',
                ],
                'tls' => $this->tlsSettings(),
                'hop_interval' => 30,
            ],
            Server::TYPE_TUIC => [
                'version' => 5,
                'congestion_control' => 'cubic',
                'alpn' => ['h3'],
                'udp_relay_mode' => 'native',
                'tls' => $this->tlsSettings(),
            ],
            Server::TYPE_ANYTLS => [
                'alpn' => ['h3'],
                'tls' => $this->tlsSettings(),
            ],
            Server::TYPE_SOCKS, Server::TYPE_NAIVE, Server::TYPE_HTTP => [
                'tls' => 1,
                'tls_settings' => $this->tlsSettings(),
            ],
            Server::TYPE_MIERU => [
                'transport' => 'TCP',
                'traffic_pattern' => '',
            ],
            default => [],
        };
    }

    private function tlsSettings(): array
    {
        return [
            'server_name' => 'tls.example.com',
            'allow_insecure' => true,
        ];
    }
}
