<?php
// Heading
$_['heading_title']               = 'EnviaTodo';

// Tabs
$_['tab_settings']                = 'Ajustes';
$_['tab_origins']                 = 'Orígenes';
$_['tab_carriers']                = 'Paqueterías';
$_['tab_logs']                    = 'Registros';
$_['tab_docs']                    = 'Documentación';

// Text
$_['text_extension']              = 'Extensiones';
$_['text_success']                = '¡Éxito! Has modificado el envío EnviaTodo.';
$_['text_edit']                   = 'Editar EnviaTodo';
$_['text_environment_sandbox']    = 'Pruebas / QA (apiqav2.enviatodo.mx)';
$_['text_environment_production'] = 'Producción (apiv2.enviatodo.mx)';
$_['text_select_origin']          = '-- Ninguno (configura un origen primero) --';
$_['text_strategy_aggregate']     = 'Peso agregado + caja envolvente (un solo paquete)';
$_['text_strategy_per_item']      = 'Un paquete por artículo del carrito';
$_['text_log_off']                = 'Desactivado';
$_['text_log_error']              = 'Sólo errores';
$_['text_log_info']               = 'Información';
$_['text_log_debug']              = 'Depuración (detallado)';
$_['text_none']                   = 'Ninguno';
$_['text_all_zones']              = 'Todas las zonas';
$_['text_testing']                = 'Probando conexión…';
$_['text_test_ok']                = 'Conexión exitosa — saldo: %s.';
$_['text_origin_saved']           = 'Origen guardado.';
$_['text_origin_deleted']         = 'Origen eliminado.';
$_['text_carriers_refreshed']     = 'Caché de paqueterías actualizado (%s paqueterías).';
$_['text_no_origins']             = 'Aún no hay orígenes. Haz clic en "Agregar origen" para crear uno.';
$_['text_no_carriers']            = 'Aún no hay paqueterías en caché. Haz clic en "Actualizar" para traer la lista desde enviatodo.mx.';
$_['text_no_logs']                = 'Aún no hay entradas de registro.';
$_['text_default']                = 'Predeterminado';
$_['text_confirm_delete']         = '¿Eliminar este origen?';

// Entry
$_['entry_environment']           = 'Entorno';
$_['entry_token_sandbox']         = 'Token API Sandbox';
$_['entry_token_production']      = 'Token API Producción';
$_['entry_client_id']             = 'Client ID';
$_['help_client_id']              = 'Tu ID de cliente en EnviaTodo (ej. 88950). Requerido para cancelar guías.';
$_['entry_base_url_override']     = 'URL base personalizada';
$_['entry_default_origin']        = 'Origen Predeterminado';
$_['entry_package_strategy']      = 'Estrategia de Paquete';
$_['entry_tax_class']             = 'Clase de Impuesto';
$_['entry_geo_zone']              = 'Zona Geográfica';
$_['entry_status']                = 'Estado';
$_['entry_sort_order']            = 'Orden';
$_['entry_log_level']             = 'Nivel de Registro';
$_['entry_test_connection']       = 'Probar Conexión';

// Entry — Origen
$_['entry_origin_name']           = 'Nombre';
$_['entry_origin_contact']        = 'Contacto';
$_['entry_origin_phone']          = 'Teléfono';
$_['entry_origin_email']          = 'Correo';
$_['entry_origin_street']         = 'Calle';
$_['entry_origin_number']         = 'Número';
$_['entry_origin_district']       = 'Colonia';
$_['entry_origin_city']           = 'Ciudad';
$_['entry_origin_state']          = 'Estado';
$_['entry_origin_postal_code']    = 'Código postal';
$_['entry_origin_country']        = 'País (ISO-2)';
$_['entry_origin_is_default']     = 'Usar como origen predeterminado';

// Encabezados — Orígenes / Paqueterías / Registros
$_['column_origin_name']          = 'Nombre';
$_['column_origin_address']       = 'Dirección';
$_['column_origin_postal_code']   = 'Código postal';
$_['column_origin_action']        = 'Acción';
$_['column_carrier_code']         = 'Código';
$_['column_carrier_name']         = 'Nombre';
$_['column_carrier_refreshed']    = 'Actualizado';
$_['column_log_date']             = 'Fecha';
$_['column_log_level']            = 'Nivel';
$_['column_log_endpoint']         = 'Endpoint';

// Botones
$_['button_add_origin']           = 'Agregar origen';
$_['button_edit']                 = 'Editar';
$_['button_delete']               = 'Eliminar';
$_['button_save']                 = 'Guardar';
$_['button_cancel']               = 'Cancelar';
$_['button_refresh_carriers']     = 'Actualizar paqueterías';

// Help
$_['help_origins_empty']          = 'Aún no hay orígenes configurados. Agrega uno en la pestaña "Orígenes" antes de cotizar.';
$_['help_base_url_override']      = 'Opcional. Sobrescribe la URL base (p. ej. <code>https://apiqav3.enviatodo.mx/index.php</code>). Déjalo vacío para usar el endpoint estándar QA/Producción del entorno seleccionado.';
$_['help_origins_phase']          = 'La gestión de orígenes se agrega en la Fase 4.';
$_['help_carriers_phase']         = 'La lista de paqueterías se actualiza desde /carriers en las Fases 3 (Probar conexión) / 5.';
$_['help_logs_phase']             = 'El visor de registros de la API se agrega en la Fase 2.';

// Error
$_['error_permission']            = 'Atención: No tienes permiso para modificar el envío EnviaTodo.';
$_['error_token_missing']         = 'El token API está vacío para el entorno seleccionado. Guarda el formulario (o llena el campo del token) antes de probar la conexión.';
$_['error_token_for_env']         = 'No se puede activar EnviaTodo: el token de %s está vacío. Pega un token para ese entorno o cambia el selector de entorno antes de guardar.';
$_['error_origin_not_found']      = 'Origen no encontrado.';
$_['error_origin_name']           = 'El nombre es obligatorio.';
$_['error_origin_postal_code']    = 'El código postal es obligatorio.';
$_['error_origin_country']        = 'El país es obligatorio.';

// Documentación
$_['docs_settings']               = 'Configuración general. Guarda el formulario antes de probar la conexión o actualizar paqueterías.';
$_['docs_environment']            = 'Cambia entre el entorno de pruebas EnviaTodo (apiqav2.enviatodo.mx) y producción (apiv2.enviatodo.mx). Los tokens son independientes para cada uno.';
$_['docs_token']                  = 'Token JWT emitido en tu portal de cliente EnviaTodo. Los tokens de pruebas solo funcionan en sandbox; los de producción solo en producción.';
$_['docs_base_url_override']      = 'Opcional. Sobrescribe la URL base (p. ej. apiqav3.enviatodo.mx) cuando EnviaTodo te indique apuntar a un host distinto. Déjalo vacío para usar el endpoint estándar del entorno seleccionado.';
$_['docs_default_origin']         = 'Origen utilizado al generar cotizaciones en el sitio. Agrega al menos un origen en la pestaña Orígenes y selecciónalo aquí.';
$_['docs_strategy']               = 'Cómo se convierte el carrito en paquete: peso agregado dentro de una caja envolvente (por defecto) o un paquete por artículo. Agregado suele ser más económico.';
$_['docs_log_level']              = 'Desactivado | Sólo errores | Info | Depuración. Los registros se guardan en la base de datos y se duplican en storage/logs/enviatodo.log para verlos también en Sistema > Mantenimiento > Registros.';
$_['docs_origins_intro']          = 'Un "origen" es la dirección de almacén / punto de recolección desde el que se despachan los envíos. EnviaTodo calcula tarifas y agenda recolecciones desde esta dirección. Puedes registrar varios orígenes (almacenes, sucursales) pero solo uno es predeterminado a la vez.';
$_['docs_origins_default']        = 'El origen predeterminado se utiliza en todas las cotizaciones del checkout. Para cambiar el predeterminado, edita otro origen y activa "Usar como origen predeterminado" — el anterior se desmarca automáticamente.';
$_['docs_origins_required']       = 'Campos obligatorios: nombre, código postal y país (ISO-2). Teléfono, correo, calle y número son altamente recomendados porque EnviaTodo los pide al programar una recolección.';
$_['docs_carriers_intro']         = 'Caché local de las paqueterías y servicios que EnviaTodo puede usar con tu cuenta. Los códigos mostrados (provider_id) son los valores usados en las llamadas de cotización / generación.';
$_['docs_carriers_refresh']       = 'Haz clic en "Actualizar paqueterías" para llamar a Api/get_parcel_service y reconstruir el caché. Vuelve a actualizar cuando EnviaTodo active una nueva paquetería en tu cuenta.';
$_['docs_logs_intro']             = 'Últimas 50 entradas de pares request/response de la API. Útil cuando una cotización no devuelve tarifas o falla la generación de guías.';
$_['docs_logs_levels']            = 'Configura el nivel de registro en la pestaña Ajustes. "Sólo errores" es el valor recomendado en producción; pasa a Depuración temporalmente cuando necesites payloads completos.';
$_['docs_logs_files']             = 'Las mismas líneas se agregan a <code>storage/logs/enviatodo.log</code> — ábrelo desde <em>Sistema &gt; Mantenimiento &gt; Registros</em>.';
$_['docs_help_title']             = '¿Necesitas ayuda?';
$_['docs_help_body']              = 'Colección Postman EnviaTodo: <a href="https://documenter.getpostman.com/view/" target="_blank">documenter.getpostman.com</a>. Soporte: <a href="mailto:soporte@enviatodo.mx">soporte@enviatodo.mx</a>.';
$_['docs_quickstart_title']       = 'Inicio rápido';
$_['docs_quickstart_step1']       = 'Pega tu token de pruebas en Ajustes.';
$_['docs_quickstart_step2']       = 'Haz clic en "Probar conexión" — debería mostrar tu saldo.';
$_['docs_quickstart_step3']       = 'Agrega un origen (almacén) y márcalo como predeterminado.';
$_['docs_quickstart_step4']       = 'Actualiza paqueterías desde la pestaña Paqueterías.';
$_['docs_quickstart_step5']       = 'Activa la extensión y coloca un pedido de prueba.';

// Pre-llenado de origen (importar desde Tienda / Sucursal)
$_['entry_origin_prefill']        = 'Importar desde';
$_['help_origin_prefill']         = 'Elige una Tienda o una Sucursal existente para pre-llenar los campos. Puedes ajustar cualquier campo antes de guardar.';
$_['text_origin_prefill_choose']  = '— Selecciona origen —';
$_['text_source_store']           = 'Tienda';
$_['text_source_location']        = 'Sucursal';

// Fase 6 — panel de pedido
$_['text_shipping_method']     = 'Método de envío';
$_['text_shipping_code']       = 'Código de envío';
$_['text_status']              = 'Estado';
$_['text_tracking']            = 'Número de guía';
$_['text_carrier']             = 'Paquetería';
$_['text_service']             = 'Servicio';
$_['text_label']               = 'Guía';
$_['text_cost']                = 'Costo';
$_['text_no_shipment']         = 'Aún no se ha generado ningún envío.';
$_['text_no_rates']            = 'No hay tarifas disponibles.';
$_['text_not_enviatodo_order'] = 'Este pedido no se realizó con una tarifa EnviaTodo. Puedes recotizar y generar la guía abajo.';
$_['text_confirm_cancel']      = '¿Cancelar este envío? Esta acción no se puede deshacer.';
$_['text_label_generated']     = 'Guía generada.';
$_['text_label_ready']         = 'Guía lista.';
$_['text_shipment_cancelled']  = 'Envío cancelado.';
$_['text_tracking_refreshed']  = 'Estado de guía actualizado.';
$_['button_open_label']        = 'Abrir guía';
$_['button_download_label']    = 'Descargar guía';
$_['button_refresh_tracking']  = 'Actualizar estado';
$_['button_cancel_shipment']   = 'Cancelar envío';
$_['button_generate_label']    = 'Generar guía';
$_['button_requote']           = 'Recotizar';
$_['error_shipment_order_id']  = 'Falta el order_id o es inválido.';
$_['error_no_uuid']            = 'No se pudo obtener un uuid de cotización para este pedido.';
$_['error_rate_not_found']     = 'El proveedor/servicio elegido ya no está disponible para este pedido. Recotiza y selecciona otro.';
$_['error_no_shipment']        = 'Aún no hay un envío registrado para este pedido.';
$_['error_no_guide']           = 'Este envío no tiene guide id; no se puede descargar la guía.';
$_['error_no_label_url']       = 'EnviaTodo no devolvió una URL de guía.';
