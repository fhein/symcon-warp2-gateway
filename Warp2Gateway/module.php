<?php
declare(strict_types=1);

require_once __DIR__ . '/../libs/ModuleRegistration.php';
require_once __DIR__ . '/Warp2API.php';

class Warp2Gateway extends IPSModule
{
    protected $api = null;

    public function __construct($InstanceID)
    {
        parent::__construct($InstanceID);
        $this->api = new Warp2API();
    }

    public function Create()
    {
        parent::Create();
        try {
            $mr = new WARP2ModuleRegistration($this);
            $config = include __DIR__ . '/module.config.php';
            $mr->Register($config);
            $this->RegisterTimer('Update', 0, 'WARP2_Update($_IPS[\'TARGET\']);');
        } catch (Exception $e) {
            $this->LogMessage(__CLASS__, "Error creating Warp2 Gateway: " . $e->getMessage(), KL_ERROR);
        }
    }

    public function Destroy() {
        parent::Destroy();
        $this->SetTimerInterval('Update', 0);
        $config = include __DIR__ . '/module.config.php';
        if (isset($config['profiles'])) {
            $mr = new WARP2ModuleRegistration($this);
            $mr->DeleteProfiles($config['profiles']);
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        try {
            $this->api->init($this->getConfig());
            $enabled = $this->ReadPropertyBoolean('enabled');
            $updateInterval = $enabled ? $this->ReadPropertyInteger('updateInterval') : 0;
            $this->SetTimerInterval('Update', $updateInterval * 1000);
            // remove legacy action variables no longer exposed
            $this->cleanupLegacyVariables();
            $this->SetStatus(102);
        } catch (Exception $e) {
            $this->SetStatus(104);
            $this->LogMessage("Error initializing Warp2 Gateway: " . $e->getMessage(), KL_ERROR);
        }
    }

    private function cleanupLegacyVariables(): void
    {
        foreach (['update_now','reboot'] as $ident) {
            try { if (@$this->GetIDForIdent($ident)) { @$this->UnregisterVariable($ident); } } catch (Throwable $t) { /* ignore */ }
        }
    }

    public function Update() {
        try {
            $response = $this->api->apiRequest($this->getConfig(), 'evse/state');
            $values = json_decode($response, true);
            if (!is_array($values)) {
                throw new Exception('Invalid response from Warp2 API');
            }
            foreach ($values as $key => $value) {
                if (@$this->GetIDForIdent($key)) {
                    $this->SetValue($key, $value);
                }
            }
            $this->SetStatus(102);
        } catch (Exception $e) {
            $this->LogMessage("Warp2 API Error: " . $e->getMessage(), KL_ERROR);
            $this->SetStatus(104);
        }
    }

    public function ForwardData($json) {
        try {
            $data = json_decode($json);
            $result = $this->api->apiRequest($this->getConfig(), $data->endpoint, $data->method ?? 'GET', $data->payload ?? null);
            return $result;
        } catch (Exception $e) {
            $this->LogMessage("Warp2 API Error: " . $e->getMessage(), KL_ERROR);
            return '{}';
        }
    }

    public function GetMeterState(): ?array
    {
        return $this->fetchMeterEndpoint('meter/state');
    }

    public function GetMeterValues(): ?array
    {
        return $this->fetchMeterEndpoint('meter/values');
    }

    public function RequestAction($ident, $value) {
        if (@$this->GetIDForIdent($ident)) {
            $this->SetValue($ident, $value);
        }
        try {
            switch ($ident) {
                case 'target_current':
                    $payload = [ 'current' => (int)$value ];
                    $this->api->apiRequest($this->getConfig(), 'evse/global_current', 'PUT', $payload);
                    break;
                case 'update_now':
                    $this->Update();
                    break;
                case 'reboot':
                    $this->api->apiRequest($this->getConfig(), 'force_reboot', 'PUT', []);
                    break;
                default:
                    throw new Exception('Unsupported action: ' . $ident);
            }
        } catch (Exception $e) {
            $this->LogMessage('Action failed: ' . $e->getMessage(), KL_ERROR);
        }
    }

    protected function getConfig()
    {
        return [
            'host'     => $this->ReadPropertyString('host'),
            'user'     => $this->ReadPropertyString('user'),
            'password' => $this->ReadPropertyString('password'),
        ];
    }

    private function fetchMeterEndpoint(string $endpoint): ?array
    {
        try {
            $response = $this->api->apiRequest($this->getConfig(), $endpoint);
            $decoded = json_decode($response, true);
            return is_array($decoded) ? $decoded : null;
        } catch (Exception $e) {
            $this->SendDebug('Warp2Gateway', sprintf('Meter endpoint %s failed: %s', $endpoint, $e->getMessage()), 0);
            return null;
        }
    }
}
