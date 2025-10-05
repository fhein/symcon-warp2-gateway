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
            $this->SetBuffer('hardwareMaxCurrent', '0');
            // Register periodic update timer (disabled by default; configured in ApplyChanges)
            $this->RegisterTimer('UpdateTimer', 0, 'WARP2_Update($_IPS["TARGET"]);');
            $this->SetBuffer('timerInit', '1');
        } catch (Exception $e) {
            $this->LogMessage(__CLASS__, "Error creating Warp2 Gateway: " . $e->getMessage(), KL_ERROR);
        }
    }

    public function Destroy() {
        parent::Destroy();
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
            // Ensure variables/profiles are (re)registered on updates so new idents appear on existing instances
            $config = include __DIR__ . '/module.config.php';
            $mr = new WARP2ModuleRegistration($this);
            if (isset($config['profiles'])) {
                $mr->RegisterProfiles($config['profiles']);
            }
            if (isset($config['variables'])) {
                $mr->RegisterVariables($config['variables']);
            }
            $this->api->init($this->getConfig());
            $this->fetchStaticChargerInfo();
            // remove legacy action variables no longer exposed
            $this->cleanupLegacyVariables();
            @ $this->SetTimerInterval('UpdateTimer', 2000);
            // Prime state once on apply
            $this->Update();
            $this->SetStatus(102);
        } catch (Exception $e) {
            $this->SetStatus(104);
            $this->LogMessage("Error initializing Warp2 Gateway: " . $e->getMessage(), KL_ERROR);
        }
    }

    private function fetchStaticChargerInfo(): void
    {
        $config = $this->getConfig();

        try {
            $nameInfo = $this->api->apiRequest($config, 'info/name');
            $nameData = json_decode($nameInfo, true);
            if (is_array($nameData) && isset($nameData['display_type'])) {
                $this->setVariableIfExists('charger_model', (string)$nameData['display_type']);
            }
        } catch (Exception $e) {
            $this->SendDebug('Warp2Gateway', 'fetchStaticChargerInfo (name) failed: ' . $e->getMessage(), 0);
        }

        try {
            $versionInfo = $this->api->apiRequest($config, 'info/version');
            $versionData = json_decode($versionInfo, true);
            if (is_array($versionData)) {
                if (isset($versionData['firmware'])) {
                    $this->setVariableIfExists('firmware_version', (string)$versionData['firmware']);
                }
                if (isset($versionData['config'])) {
                    $this->setVariableIfExists('config_version', (string)$versionData['config']);
                }
                if (isset($versionData['config_type'])) {
                    $this->setVariableIfExists('config_type', (string)$versionData['config_type']);
                }
            }
        } catch (Exception $e) {
            $this->SendDebug('Warp2Gateway', 'fetchStaticChargerInfo (version) failed: ' . $e->getMessage(), 0);
        }

        $hardwareUpdated = false;
        try {
            $hardwareInfo = $this->api->apiRequest($config, 'evse/hardware_configuration');
            $hardwareData = json_decode($hardwareInfo, true);
            if (is_array($hardwareData) && isset($hardwareData['jumper_configuration'])) {
                $code = (int)$hardwareData['jumper_configuration'];
                $hardwareMax = $this->mapJumperConfigToCurrent($code);
                if ($hardwareMax === null) {
                    $this->SendDebug('Warp2Gateway', sprintf('Jumper configuration %d not mapped to fixed current; using fallback.', $code), 0);
                }
                $this->updateHardwareMaxCurrent($hardwareMax);
                $hardwareUpdated = true;
            }
        } catch (Exception $e) {
            $this->SendDebug('Warp2Gateway', 'fetchStaticChargerInfo (hardware) failed: ' . $e->getMessage(), 0);
        }

        if (!$hardwareUpdated) {
            $this->updateHardwareMaxCurrent(null);
        }
    }

    private function setVariableIfExists(string $ident, $value): void
    {
        $varId = @$this->GetIDForIdent($ident);
        if ($varId !== false) {
            $this->SetValue($ident, $value);
        }
    }

    private function updateHardwareMaxCurrent(?int $value): void
    {
        $current = $value ?? 0;
        $this->SetBuffer('hardwareMaxCurrent', (string)$current);
        $this->setVariableIfExists('hardware_max_current', $current);
    }

    private function mapJumperConfigToCurrent(int $code): ?int
    {
        $map = [
            0 => 6000,
            1 => 10000,
            2 => 13000,
            3 => 16000,
            4 => 20000,
            5 => 25000,
            6 => 32000,
        ];

        return $map[$code] ?? null;
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
            // Charger power: prefer meter values; else compute from allowed current (mA -> W)
            $power = null;
            $meterValues = $this->fetchMeterEndpoint('meter/values');
            if (is_array($meterValues) && isset($meterValues['power']) && is_numeric($meterValues['power'])) {
                $power = (float)$meterValues['power'];
            }
            if ($power === null) {
                $allowed = isset($values['allowed_charging_current']) && is_numeric($values['allowed_charging_current'])
                    ? (int)$values['allowed_charging_current'] : 0;
                if ($allowed > 0) {
                    $power = ($allowed * 3.0 * 230.0) / 1000.0;
                } else {
                    $power = 0.0;
                }
            }
            if (@$this->GetIDForIdent('charger_power')) {
                $this->SetValue('charger_power', (float)$power);
            }
            $this->SetStatus(102);
        } catch (Exception $e) {
            $this->LogMessage("Warp2 API Error: " . $e->getMessage(), KL_ERROR);
            $this->SetStatus(104);
        }
    }

    // No generic meter power helper needed; Update() reads meter/values['power'] directly

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
        // Button-Variablen (Boolean Actions) automatisch zurücksetzen
        if (@$this->GetIDForIdent($ident)) {
            if (is_bool($value)) {
                $this->SetValue($ident, false);
            } else {
                $this->SetValue($ident, $value);
            }
        }

        try {
            switch ($ident) {
                // Neue Steuerungs-Buttons
                case 'mxccmd_apply_now':
                    $this->applyCurrentFromCommand();
                    break;
                case 'mxccmd_refresh':
                    $this->Update();
                    break;
                case 'mxccmd_reboot':
                    $this->api->apiRequest($this->getConfig(), 'force_reboot', 'PUT', []);
                    break;

                // Legacy-Unterstützung (Buttons/Actions aus früheren Versionen)
                case 'update_now':
                    $this->Update();
                    break;
                case 'reboot':
                    $this->api->apiRequest($this->getConfig(), 'force_reboot', 'PUT', []);
                    break;

                // Kein Kommando auf reines Schreiben des Zielstroms auslösen
                case 'mxccmd_target_current':
                case 'target_current':
                    // Nur persistieren; Anwenden erfolgt über Apply-Button
                    break;

                default:
                    throw new Exception('Unsupported action: ' . $ident);
            }
        } catch (Exception $e) {
            $this->LogMessage('Action failed: ' . $e->getMessage(), KL_ERROR);
        }
    }

    private function applyCurrentFromCommand(): void
    {
        // Quelle priorisieren: mxccmd_target_current (neu) > target_current (legacy)
        $value = null;
        $mxccmdVar = @$this->GetIDForIdent('mxccmd_target_current');
        if ($mxccmdVar) {
            $value = @GetValueInteger($mxccmdVar);
        }
        if ($value === null) {
            $legacyVar = @$this->GetIDForIdent('target_current');
            if ($legacyVar) {
                $value = @GetValueInteger($legacyVar);
            }
        }

        if (!is_int($value)) {
            $this->SendDebug('Warp2Gateway', 'applyCurrentFromCommand: kein Zielstrom gesetzt – führe stattdessen einen Refresh aus.', 0);
            $this->Update();
            return;
        }

        // Hardware-Max beachten
        $hardwareMax = $this->GetHardwareMaxCurrent();
        $target = $value;
        if ($hardwareMax > 0) {
            $target = min($target, $hardwareMax);
        }
        $target = max(0, (int)$target);

        try {
            $this->api->apiRequest($this->getConfig(), 'evse/external_current', 'PUT', ['current' => $target]);
            $this->setVariableIfExists('target_current', $target);
            $this->SendDebug('Warp2Gateway', sprintf('Apply: External current set to %d mA.', (int)$target), 0);
        } catch (Exception $e) {
            $this->LogMessage('applyCurrentFromCommand failed: ' . $e->getMessage(), KL_ERROR);
        }

        $this->Update();
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

    public function GetHardwareMaxCurrent(): int
    {
        $buffer = @$this->GetBuffer('hardwareMaxCurrent');
        if ($buffer === '' || $buffer === null) {
            $this->fetchStaticChargerInfo();
            $buffer = @$this->GetBuffer('hardwareMaxCurrent');
        }

        return is_numeric($buffer) ? (int)$buffer : 0;
    }

    private function handleRebootCommand(): void
    {
        $this->SendDebug('Warp2Gateway', 'Received charger reboot command.', 0);
        try {
            $this->api->apiRequest($this->getConfig(), 'force_reboot', 'PUT', []);
        } catch (Exception $e) {
            $this->LogMessage('reboot failed: ' . $e->getMessage(), KL_ERROR);
        }
    }


}
