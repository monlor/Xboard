# Subscription Protocol Compatibility

This matrix documents which node protocols Xboard should emit for each built-in
subscription target. Keep it aligned with `app/Protocols/*` and client vendor
documentation before adding new protocol output.

## Compatibility Matrix

| Subscription target | Flags | Protocols emitted | Version gates and limits | Status |
| --- | --- | --- | --- | --- |
| General URI | `general`, `v2rayn`, `v2rayng`, `passwall`, `ssrplus`, `sagernet` | VMess, VLESS, Shadowsocks, Trojan, Hysteria, AnyTLS, SOCKS, TUIC, HTTP | Hysteria2 requires v2rayN 6.31+ or v2rayNG 1.9.5+ when those clients are detected. | Broad URI fallback. |
| Clash legacy | `clash` | Shadowsocks, VMess, Trojan, SOCKS, HTTP | No VLESS, Hysteria2, TUIC, or AnyTLS. Use a Mihomo/Clash Meta flag for those protocols. | Intentionally legacy. |
| Clash Meta / Mihomo | `mihomo`, `clash-meta`, `clashmeta`, `meta`, `clash-verge-rev`, `clash-verge`, `clashverge`, `verge`, `flclash`, `nekobox`, `nekoray`, `clashmetaforandroid`, `clashx meta` | Shadowsocks, VMess, Trojan, VLESS, Hysteria/Hysteria2, TUIC, AnyTLS, SOCKS, HTTP, Mieru | Transport fields are strictly limited to the networks handled by the renderer. ECH and Hysteria2 version gates apply for clients that publish a version. | Primary Clash-family target for modern protocols. |
| sing-box | `sing-box`, `hiddify`, `sfm` | Shadowsocks, Trojan, VMess, VLESS, Hysteria/Hysteria2, TUIC, AnyTLS, SOCKS, Naive, HTTP | VLESS requires 1.5.0+, Reality 1.6.0+, Hysteria2/TUIC 1.5.0+, AnyTLS 1.12.0+, Naive 1.8.0+. | Modern JSON target. |
| Stash | `stash` | Shadowsocks, VMess, VLESS, Hysteria/Hysteria2, Trojan, TUIC, AnyTLS, SOCKS, HTTP | AnyTLS 3.3.0+, VLESS Reality 3.1.0+, Hysteria2 2.5.0+, TUIC 2.3.0+. Trojan Reality and VMess HTTPUpgrade are filtered. | YAML target with Stash-specific gates. |
| Surge | `surge` | Shadowsocks, VMess, Trojan, Hysteria2, TUIC, AnyTLS, SOCKS, HTTP | No VLESS. Hysteria2 requires Surge 5.8.0+, AnyTLS requires 5.17.0+. HY2 Salamander obfs emits `salamander-password`; port hopping emits `port-hopping` and `port-hopping-interval`. | Profile target for Surge. |
| Shadowrocket | `shadowrocket` | Shadowsocks, VMess, VLESS, Trojan, Hysteria/Hysteria2, TUIC, AnyTLS, SOCKS | Hysteria2 requires build 1993+, AnyTLS build 2592+. Trojan transport is strictly limited to TCP/WS/gRPC/H2/HTTPUpgrade. | URI target with build gates. |
| Loon | `loon` | Shadowsocks, VMess, Trojan, Hysteria2, VLESS, AnyTLS | Hysteria must be v2 and requires build 637+. AnyTLS requires build 945+. Trojan Reality is filtered. | Loon text target. |
| Quantumult X | `quantumult%20x`, `quantumult-x` | Shadowsocks, VMess, VLESS, Trojan, AnyTLS, SOCKS, HTTP | No Hysteria/TUIC output in current renderer. | URI target. |
| Surfboard | `surfboard` | Shadowsocks, VMess, Trojan, AnyTLS | No VLESS/Hysteria/TUIC output in current renderer. | Surge-like profile target. |
| SIP008 Shadowsocks | `shadowsocks` | Shadowsocks | AEAD ciphers only. | SIP008 JSON target. |

## Source References

- Surge Proxy Policy: https://manual.nssurge.com/policy/proxy.html
- sing-box outbound configuration: https://sing-box.sagernet.org/configuration/outbound/
- Stash protocol types: https://stash.wiki/proxy-protocols/proxy-types
- Loon node support: https://nsloon.app/docs/Node/
- Mihomo Hysteria2: https://wiki.metacubex.one/en/config/proxies/hysteria2/
- Mihomo AnyTLS: https://wiki.metacubex.one/en/config/proxies/anytls/
