<?php
class ServerManagerService {

    private $whmHost;
    private $whmUsername;
    private $apiToken;

    public function __construct($whmHost, $whmUsername, $apiToken) {
        $this->whmHost = rtrim($whmHost, '/');
        $this->whmUsername = $whmUsername;
        $this->apiToken = $apiToken;
    }

    // Auto-issue Let's Encrypt / cPanel AutoSSL for a domain
    public function triggerAutoSSL($cpanelUser) {
        $url = "{$this->whmHost}:2087/json-api/cpanel?cpanel_jsonapi_user={$cpanelUser}&cpanel_jsonapi_apiversion=3&cpanel_jsonapi_module=AutoSSL&cpanel_jsonapi_func=start_autossl_check";
        return $this->callWhmApi($url);
    }

    // Check Domain DNS Propagation / Records
    public function verifyDomainDns($domain, $expectedIp) {
        $dnsRecords = dns_get_record($domain, DNS_A);
        if (!$dnsRecords) return false;

        foreach ($dnsRecords as $record) {
            if (isset($record['ip']) && $record['ip'] === $expectedIp) {
                return true;
            }
        }
        return false;
    }

    // Set CloudLinux Resource Limits (CPU/RAM/IO)
    public function setCloudLinuxLimits($username, $speedLimit = '100', $pmemLimit = '1024M') {
        $url = "{$this->whmHost}:2087/json-api/cloudlinux_set_user_limits?api.version=1&user={$username}&speed={$speedLimit}&pmem={$pmemLimit}";
        return $this->callWhmApi($url);
    }

    private function callWhmApi($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: whm {$this->whmUsername}:{$this->apiToken}"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}