<?php
// Heading
$_['heading_title']               = 'EnviaTodo';

// Tabs
$_['tab_settings']                = 'Settings';
$_['tab_origins']                 = 'Origins';
$_['tab_carriers']                = 'Carriers';
$_['tab_logs']                    = 'Logs';
$_['tab_docs']                    = 'Docs';

// Text
$_['text_extension']              = 'Extensions';
$_['text_success']                = 'Success: You have modified EnviaTodo shipping!';
$_['text_edit']                   = 'Edit EnviaTodo Shipping';
$_['text_environment_sandbox']    = 'Sandbox / QA (apiqav2.enviatodo.com)';
$_['text_environment_production'] = 'Production (api.enviatodo.com)';
$_['text_select_origin']          = '-- None (configure an origin first) --';
$_['text_strategy_aggregate']     = 'Aggregate weight + bounding box (single package)';
$_['text_strategy_per_item']      = 'One package per cart item';
$_['text_log_off']                = 'Off';
$_['text_log_error']              = 'Errors only';
$_['text_log_info']               = 'Info';
$_['text_log_debug']              = 'Debug (verbose)';
$_['text_none']                   = 'None';
$_['text_all_zones']              = 'All Zones';
$_['text_testing']                = 'Testing connection…';
$_['text_test_ok']                = 'Connection OK — balance: %s.';
$_['text_origin_saved']           = 'Origin saved.';
$_['text_origin_deleted']         = 'Origin deleted.';
$_['text_carriers_refreshed']     = 'Carrier cache refreshed (%s carriers).';
$_['text_no_origins']             = 'No origins yet. Click "Add origin" to create one.';
$_['text_no_carriers']            = 'No carriers cached yet. Click "Refresh" to pull the list from enviatodo.com.';
$_['text_no_logs']                = 'No log entries yet.';
$_['text_default']                = 'Default';
$_['text_confirm_delete']         = 'Delete this origin?';

// Entry
$_['entry_environment']           = 'Environment';
$_['entry_token_sandbox']         = 'Sandbox API Token';
$_['entry_token_production']      = 'Production API Token';
$_['entry_client_id']             = 'Client ID';
$_['help_client_id']              = 'Your EnviaTodo customer/account ID (e.g. 88950). Required to cancel orders.';
$_['entry_base_url_override']     = 'Base URL override';
$_['entry_default_origin']        = 'Default Origin';
$_['entry_package_strategy']      = 'Package Strategy';
$_['entry_tax_class']             = 'Tax Class';
$_['entry_geo_zone']              = 'Geo Zone';
$_['entry_status']                = 'Status';
$_['entry_sort_order']            = 'Sort Order';
$_['entry_log_level']             = 'Log Level';
$_['entry_test_connection']       = 'Test Connection';

// Entry — Origin form
$_['entry_origin_name']           = 'Name';
$_['entry_origin_contact']        = 'Contact';
$_['entry_origin_phone']          = 'Phone';
$_['entry_origin_email']          = 'Email';
$_['entry_origin_street']         = 'Street';
$_['entry_origin_number']         = 'Number';
$_['entry_origin_district']       = 'District / Colonia';
$_['entry_origin_city']           = 'City';
$_['entry_origin_state']          = 'State';
$_['entry_origin_postal_code']    = 'Postal code';
$_['entry_origin_country']        = 'Country (ISO-2)';
$_['entry_origin_is_default']     = 'Use as default origin';

// Column headers — Origins / Carriers / Logs
$_['column_origin_name']          = 'Name';
$_['column_origin_address']       = 'Address';
$_['column_origin_postal_code']   = 'Postal code';
$_['column_origin_action']        = 'Action';
$_['column_carrier_code']         = 'Code';
$_['column_carrier_name']         = 'Name';
$_['column_carrier_refreshed']    = 'Refreshed';
$_['column_log_date']             = 'Date';
$_['column_log_level']            = 'Level';
$_['column_log_endpoint']         = 'Endpoint';

// Buttons
$_['button_add_origin']           = 'Add origin';
$_['button_edit']                 = 'Edit';
$_['button_delete']               = 'Delete';
$_['button_save']                 = 'Save';
$_['button_cancel']               = 'Cancel';
$_['button_refresh_carriers']     = 'Refresh carriers';

// Help
$_['help_origins_empty']          = 'No origins configured yet. Add one in the "Origins" tab before quoting.';
$_['help_base_url_override']      = 'Optional. Override the base URL (e.g. <code>https://apiqav3.enviatodo.com/index.php</code>). Leave blank to use the standard QA/Production endpoint for the selected environment.';
$_['help_origins_phase']          = 'Origins management UI is added in Phase 4.';
$_['help_carriers_phase']         = 'Carrier list refreshed from /carriers in Phase 3 (Test connection) / Phase 5.';
$_['help_logs_phase']             = 'API request/response log viewer is added in Phase 2.';

// Error
$_['error_permission']            = 'Warning: You do not have permission to modify EnviaTodo shipping!';
$_['error_token_missing']         = 'API token is empty for the selected environment. Save the form (or fill the token field) before testing the connection.';
$_['error_token_for_env']         = 'Cannot enable EnviaTodo: the %s token is empty. Either paste a token for that environment or switch the environment selector before saving.';
$_['error_origin_not_found']      = 'Origin not found.';
$_['error_origin_name']           = 'Name is required.';
$_['error_origin_postal_code']    = 'Postal code is required.';
$_['error_origin_country']        = 'Country is required.';

// Docs tab
$_['docs_settings']               = 'Top-level configuration. Save the form before testing connection or pulling carriers.';
$_['docs_environment']            = 'Switches between EnviaTodo sandbox (apiqav2.enviatodo.com) and production (api.enviatodo.com). Tokens are independent for each.';
$_['docs_token']                  = 'JWT bearer token issued in your EnviaTodo client portal. Sandbox tokens only work against sandbox; production tokens against production.';
$_['docs_base_url_override']      = 'Optional. Pin a specific host (e.g. apiqav3.enviatodo.com) when EnviaTodo asks you to point to a non-default endpoint. Leave empty to use the default for the selected environment.';
$_['docs_default_origin']         = 'The origin used when generating live quotes for the storefront. Add at least one origin in the Origins tab and pick it here.';
$_['docs_strategy']               = 'How the cart is converted into a parcel: aggregate weights into a single bounding-box package (default), or one parcel per cart line. Aggregate is cheaper for most stores.';
$_['docs_log_level']              = 'Off | Errors only | Info | Debug. Logs are persisted in the DB and mirrored to system/storage/logs/enviatodo.log so you can also view them under System > Maintenance > Logs.';
$_['docs_origins_intro']          = 'An "origin" is a warehouse / pickup address from which parcels ship out. EnviaTodo computes rates and dispatches pickups based on this address. You can register multiple origins (warehouses, drop-off points) but only one is the default at any time.';
$_['docs_origins_default']        = 'The default origin is used by the storefront for every checkout quote. To switch defaults edit any other origin and tick "Use as default origin" — the previous default is unset automatically.';
$_['docs_origins_required']       = 'Required fields: name, postal code and country (ISO-2). Phone, email, street and number are highly recommended because EnviaTodo asks for them when a pickup is scheduled.';
$_['docs_carriers_intro']         = 'Local cache of the carriers and services EnviaTodo can dispatch with under your account. Codes shown here (provider_id) are the values used in rate / generate calls.';
$_['docs_carriers_refresh']       = 'Click "Refresh carriers" to call Api/get_parcel_service and rebuild the cache. Re-run after EnviaTodo enables a new courier on your account.';
$_['docs_logs_intro']             = 'Last 50 entries of API request/response pairs. Useful when a quote returns no rates or a label fails to generate.';
$_['docs_logs_levels']            = 'Set the log level on the Settings tab. "Errors only" is the recommended default in production; switch to Debug temporarily when you need full request/response payloads.';
$_['docs_logs_files']             = 'Same lines are also appended to <code>storage/logs/enviatodo.log</code> — open it from <em>System &gt; Maintenance &gt; Logs</em>.';
$_['docs_help_title']             = 'Need help?';
$_['docs_help_body']              = 'EnviaTodo Postman collection: <a href="https://documenter.getpostman.com/view/" target="_blank">documenter.getpostman.com</a>. Account questions: <a href="mailto:soporte@enviatodo.com">soporte@enviatodo.com</a>.';
$_['docs_quickstart_title']       = 'Quick start';
$_['docs_quickstart_step1']       = 'Paste your sandbox token in Settings.';
$_['docs_quickstart_step2']       = 'Click "Test connection" — it should report your balance.';
$_['docs_quickstart_step3']       = 'Add an origin (warehouse) and mark it default.';
$_['docs_quickstart_step4']       = 'Refresh carriers from the Carriers tab.';
$_['docs_quickstart_step5']       = 'Enable the extension and place a test order.';

// Origin prefill (import from Store / Locations)
$_['entry_origin_prefill']        = 'Import from';
$_['help_origin_prefill']         = 'Choose an existing Store or Location to pre-fill the form below. You can still adjust any field before saving.';
$_['text_origin_prefill_choose']  = '— Select source —';
$_['text_source_store']           = 'Store';
$_['text_source_location']        = 'Location';

// Phase 6 — order panel
$_['text_shipping_method']     = 'Shipping method';
$_['text_shipping_code']       = 'Shipping code';
$_['text_status']              = 'Status';
$_['text_tracking']            = 'Tracking number';
$_['text_carrier']             = 'Carrier';
$_['text_service']             = 'Service';
$_['text_label']               = 'Label';
$_['text_cost']                = 'Cost';
$_['text_no_shipment']         = 'No shipment generated yet.';
$_['text_no_rates']            = 'No rates available.';
$_['text_not_enviatodo_order'] = 'This order was not placed with an EnviaTodo shipping rate. You can still re-quote and generate a label below.';
$_['text_confirm_cancel']      = 'Cancel this shipment? This cannot be undone.';
$_['text_label_generated']     = 'Label generated.';
$_['text_label_ready']         = 'Label ready.';
$_['text_shipment_cancelled']  = 'Shipment cancelled.';
$_['text_tracking_refreshed']  = 'Tracking refreshed.';
$_['button_open_label']        = 'Open label';
$_['button_download_label']    = 'Download label';
$_['button_refresh_tracking']  = 'Refresh tracking';
$_['button_cancel_shipment']   = 'Cancel shipment';
$_['button_generate_label']    = 'Generate label';
$_['button_requote']           = 'Re-quote';
$_['error_shipment_order_id']  = 'Missing or invalid order_id.';
$_['error_no_uuid']            = 'Could not retrieve a fresh quote uuid for this order.';
$_['error_rate_not_found']     = 'The selected provider/service is not available for this order. Re-quote and pick another.';
$_['error_no_shipment']        = 'No shipment is recorded for this order yet.';
$_['error_no_guide']           = 'This shipment has no guide id; cannot download the label.';
$_['error_no_label_url']       = 'EnviaTodo did not return a label URL.';
