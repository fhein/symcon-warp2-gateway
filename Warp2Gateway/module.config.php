<?php

return [
    'properties' => [
        'host' => [ 'type' => 'String', 'default' => 'http://192.168.11.51' ],
        'user' => [ 'type' => 'String', 'default' => '' ],
        'password' => [ 'type' => 'String', 'default' => '' ],
        'updateInterval' => [ 'type' => 'Integer', 'default' => '20' ],
        'enabled' => [ 'type' => 'Boolean', 'default' => 'true' ],
    ],
    'variables' => [
        'charger_model' => [ 'type' => 'String', 'name' => 'Charger Modell', 'profile' => '', 'position' => '1', 'enableAction' => false ],
        'firmware_version' => [ 'type' => 'String', 'name' => 'Firmware-Version', 'profile' => '', 'position' => '2', 'enableAction' => false ],
        'config_version' => [ 'type' => 'String', 'name' => 'Konfiguration-Version', 'profile' => '', 'position' => '3', 'enableAction' => false ],
    'config_type' => [ 'type' => 'String', 'name' => 'Konfiguration-Typ', 'profile' => '', 'position' => '4', 'enableAction' => false ],
    'hardware_max_current' => [ 'type' => 'Integer', 'name' => 'Maximalstrom (Hardware)', 'profile' => 'WARP2.ChargerCurrent', 'position' => '5', 'enableAction' => false ],
    'target_current' => [ 'type' => 'Integer', 'name' => 'Target Current', 'profile' => 'WARP2.ChargerCurrent', 'position' => '6', 'enableAction' => true ],
        'charger_state' => [ 'type' => 'Integer', 'name' => 'Charger Status', 'profile' => 'WARP2.ChargerState', 'position' => '10', 'enableAction' => false ],
        'iec61851_state' => [ 'type' => 'Integer', 'name' => 'Iec61851 State', 'profile' => 'WARP2.Iec61851State', 'position' => '100', 'enableAction' => false ],
        'contactor_state' => [ 'type' => 'Integer', 'name' => 'Contactor State', 'profile' => 'WARP2.ContactorState', 'position' => '102', 'enableAction' => false ],
        'contactor_error' => [ 'type' => 'Integer', 'name' => 'Contactor Error', 'profile' => 'WARP2.ContactorError', 'position' => '103', 'enableAction' => false ],
        'allowed_charging_current' => [ 'type' => 'Integer', 'name' => 'Allowed Current', 'profile' => 'WARP2.ChargerCurrent', 'position' => '104', 'enableAction' => false ],
        'error_state' => [ 'type' => 'Integer', 'name' => 'Error State', 'profile' => 'WARP2.ErrorState', 'position' => '105', 'enableAction' => false ],
        'lock_state' => [ 'type' => 'Integer', 'name' => 'Lock State', 'profile' => 'WARP2.LockState', 'position' => '106', 'enableAction' => false ],
        'dc_fault_current_state' => [ 'type' => 'Integer', 'name' => 'Dc Fault Current State', 'profile' => 'WARP2.DcFaultCurrentState', 'position' => '107', 'enableAction' => false ],
    ],
    'profiles' => [
        'WARP2.ChargerCurrent' => [ 'type' => 'Integer', 'icon' => 'Graph', 'suffix' => ' mA', 'digits' => '0' ],
        'WARP2.ChargerState' => [
            'type' => 'Integer', 'suffix' => '', 'icon' => 'Garage', 'minValue' => 0, 'maxValue' => 4, 'stepSize' => 1,
            'associations' => [
                [ 'value' => '0', 'text' => 'nicht verbunden', 'icon' => 'Cross', 'color' => -1 ],
                [ 'value' => '1', 'text' => 'warte auf Freigabe', 'icon' => 'Hourglass', 'color' => -1 ],
                [ 'value' => '2', 'text' => 'ladebereit', 'icon' => 'Hourglass', 'color' => -1 ],
                [ 'value' => '3', 'text' => 'lädt', 'icon' => 'Ok', 'color' => -1 ],
                [ 'value' => '4', 'text' => 'Fehler', 'icon' => 'Information', 'color' => -1 ],
            ]
        ],
        'WARP2.Iec61851State' => [
            'type' => 'Integer', 'suffix' => '', 'icon' => 'Garage', 'minValue' => 0, 'maxValue' => 4, 'stepSize' => 1,
            'associations' => [
                [ 'value' => '0', 'text' => 'nicht verbunden', 'icon' => 'Cross', 'color' => -1 ],
                [ 'value' => '1', 'text' => 'verbunden', 'icon' => 'Hourglass', 'color' => -1 ],
                [ 'value' => '2', 'text' => 'lädt', 'icon' => 'Ok', 'color' => -1 ],
                [ 'value' => '3', 'text' => 'lädt (mit Belüftung)', 'icon' => 'Ok', 'color' => -1 ],
                [ 'value' => '4', 'text' => 'Fehler', 'icon' => 'Information', 'color' => -1 ],
            ]
        ],
        'WARP2.ContactorState' => [
            'type' => 'Integer', 'suffix' => '', 'icon' => 'Information', 'minValue' => 0, 'maxValue' => 3, 'stepSize' => 1,
            'associations' => [
                [ 'value' => '0', 'text' => 'kein Strom vor und nach Schütz', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '1', 'text' => 'Strom vor, kein Strom nach Schütz', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '2', 'text' => 'kein Strom vor, Strom nach Schütz', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '3', 'text' => 'Strom vor und nach Schütz', 'icon' => 'Information', 'color' => -1 ],
            ]
        ],
        'WARP2.ContactorError' => [
            'type' => 'Integer', 'suffix' => '', 'icon' => 'Information', 'minValue' => 0, 'maxValue' => 6, 'stepSize' => 1,
            'associations' => [
                [ 'value' => '0', 'text' => 'kein Fehler', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '1', 'text' => 'Stromversorgung prüfen', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '2', 'text' => 'Schütz defekt?', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '3', 'text' => 'Verkabelung prüfen', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '4', 'text' => 'Stromversorgung prüfen', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '5', 'text' => 'Verkabelung prüfen', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '6', 'text' => 'Schütz defekt?', 'icon' => 'Information', 'color' => -1 ],
            ]
        ],
        'WARP2.ErrorState' => [
            'type' => 'Integer', 'suffix' => '', 'icon' => 'Information', 'minValue' => 0, 'maxValue' => 5, 'stepSize' => 1,
            'associations' => [
                [ 'value' => '0', 'text' => 'kein Fehler', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '1', 'text' => 'unbekannter Fehler', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '2', 'text' => 'Schalterfehler', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '3', 'text' => 'DC-Fehlerstrom-Fehler', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '4', 'text' => 'Schützfehler', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '5', 'text' => 'Kommunikationsfehler', 'icon' => 'Information', 'color' => -1 ],
            ]
        ],
        'WARP2.LockState' => [
            'type' => 'Integer', 'suffix' => '', 'icon' => 'Information', 'minValue' => 0, 'maxValue' => 5, 'stepSize' => 1,
            'associations' => [
                [ 'value' => '0', 'text' => 'Initialisierung', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '1', 'text' => 'Offen', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '2', 'text' => 'Schließend', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '3', 'text' => 'geschlossen', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '4', 'text' => 'Öffnend', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '5', 'text' => 'Fehler', 'icon' => 'Information', 'color' => -1 ],
            ]
        ],
        'WARP2.DcFaultCurrentState' => [
            'type' => 'Integer', 'suffix' => '', 'icon' => 'Information', 'minValue' => 0, 'maxValue' => 4, 'stepSize' => 1,
            'associations' => [
                [ 'value' => '0', 'text' => 'kein Fehler', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '1', 'text' => '6 mA Fehlerstrom erkannt', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '2', 'text' => 'Systemfehler', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '3', 'text' => 'unbekannter Fehler', 'icon' => 'Information', 'color' => -1 ],
                [ 'value' => '4', 'text' => 'Kalibrierungsfehler', 'icon' => 'Information', 'color' => -1 ],
            ]
        ],
    ],
];
