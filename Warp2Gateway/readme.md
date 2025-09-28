# WARP2 Gateway (IP-Symcon)

This IP-Symcon gateway integrates a Tinkerforge WARP2 wallbox (EVSE) via its HTTP API. It periodically polls the charger state and exposes it as IP-Symcon variables with suitable profiles. The gateway can also forward arbitrary API requests for use by child modules.

## What it does
- Connects to a WARP2 device (host/IP or URL) with optional Basic/Digest authentication.
- Polls the REST endpoint `evse/state` at a configurable interval.
- Updates a set of read-only status variables (charger state, IEC 61851 state, contactor state/error, allowed charging current, lock state, error states, etc.).
- Provides a gateway endpoint for child modules to call other WARP2 API endpoints (GET/PUT) through IP-Symcon’s data flow.

## Requirements
- A reachable WARP2 wallbox (local network or reachable URL)
- IP-Symcon instance where this module is installed

Repository URL (Module Control): https://github.com/fhein/symcon-warp2-gateway

## Installation (Module Control)
1. Open IP-Symcon Management Console.
2. Go to “Modules” and add the repository URL above.
3. Create an instance of “Warp2Gateway”.

## Configuration (form.json)
- Gateway enabled (bool): Master enable for the gateway. If disabled, no polling occurs.
- Warp2 Host (string): Base URL or IP of the device, e.g. `http://192.168.11.51`. A scheme can be included; if omitted, `http://` is assumed for reachability checks.
- User / Password (optional): Used for Basic/Digest auth against the WARP2 API.
- Update Interval (seconds) (int): Polling interval for `evse/state`.

Status codes:
- 102: connected/OK
- 104: disconnected/error

## Created variables (read-only)
The following variables are registered and updated when polling `evse/state`. They use custom profiles to present human‑readable states in the WebFront:

- charger_state (Integer, profile `WARP2.ChargerState`)
- iec61851_state (Integer, profile `WARP2.Iec61851State`)
- contactor_state (Integer, profile `WARP2.ContactorState`)
- contactor_error (Integer, profile `WARP2.ContactorError`)
- allowed_charging_current (Integer, mA, profile `WARP2.ChargerCurrent`)
- error_state (Integer, profile `WARP2.ErrorState`)
- lock_state (Integer, profile `WARP2.LockState`)
- dc_fault_current_state (Integer, profile `WARP2.DcFaultCurrentState`)

Note: The gateway maps JSON keys from `evse/state` directly to variables with the same ident. Only keys that exist as variables are updated.

## Manual refresh from a script
You can trigger an immediate poll from a script using the auto-generated wrapper (module prefix `WARP2`):

```php
WARP2_Update($InstanceID);
```

Replace `$InstanceID` with the Instance ID of your Warp2Gateway.

## Developer notes (child communication)
- The gateway implements `ForwardData($json)` so child modules can send API calls to the WARP2 device through the parent. The JSON payload must contain:
  - `endpoint` (string): e.g. `evse/state` or another WARP2 endpoint
  - `method` (string, optional): `GET` (default) or `PUT`
  - `payload` (mixed, optional): Will be JSON-encoded for `PUT`
- Authentication is handled by the gateway based on its configured user/password.
- Timeouts: connect ~2s, total request ~5s.
- HTTP errors and cURL errors are logged and set the instance status to 104.

Example payload sent from a child to the parent (conceptual):
```json
{
  "endpoint": "evse/state",
  "method": "GET"
}
```

## Troubleshooting
- Status 104 (disconnected):
  - Verify the host URL/IP is reachable from the Symcon server.
  - If using HTTPS, ensure the device supports it correctly. For local devices, `http://` is usually sufficient.
  - Check user/password if the device requires authentication.
- No variables update:
  - Make sure “Gateway enabled” is checked and the update interval is > 0.
  - Confirm the device returns valid JSON for `evse/state`.

## License
See the repository for license details.
