# EnviaTodo — OpenCart 4 Shipping Extension

OpenCart v4 shipping extension that integrates the
[EnviaTodo](https://enviatodo.com/) (envia.com) multi-carrier API into the
checkout, order management and customer tracking flows.

## Features

- **Live shipping quotes at checkout** for all carriers/services exposed by
  EnviaTodo, scoped to the configured geo zone and tax class.
- **Origin manager** — multiple pickup addresses, one default. Origins can be
  created from scratch, copied from `System → Settings → Store`, or imported
  from `System → Localisation → Locations`.
- **Carrier cache** with on-demand refresh.
- **Per-order shipment panel** in admin → Orders → View:
  - Generate label (creates shipment + downloads PDF)
  - Refresh tracking
  - Cancel shipment
- **Customer-facing tracking page** at
  `index.php?route=extension/enviatodo/shipping/enviatodo.track&order_id=N`
  with a "Track shipment" button auto-injected into the catalog
  `account/order_info` page when the order shipped via `enviatodo.*`.
- **Sandbox / production** environment switch with separate tokens.
- **Logging** to a dedicated `oc_enviatodo_log` table, mirrored to
  `System → Maintenance → Error Logs` at the configured level
  (off / error / info / debug).
- **i18n**: English (en-gb) + Spanish (es-es) parity (139 admin + 26 catalog
  strings).

## Installation

1. Go to `Extensions → Installer` in the OpenCart admin.
2. Upload `enviatodo.ocmod.zip`.
3. Go to `Extensions → Extensions → Shipping`, find **EnviaTodo** and click
   **Install**.
4. Click **Edit** to configure:
   - Environment (sandbox/production), API tokens and (optional) base URL
     override.
   - Default origin, package strategy, geo zone, tax class, sort order.
   - Log level.
5. Click **Test connection** to validate credentials.
6. Add at least one Origin and refresh Carriers.
7. Set the extension status to **Enabled** and save.

## Compatibility

- **OpenCart**: 4.x
- **PHP**: 8.0+
- **MySQL/MariaDB**: 5.7+ / 10.3+

## License

GPL-3.0

## Author

[Aztek](https://aztek.dev) — built for [ferrez.mx](https://ferrez.mx/).
