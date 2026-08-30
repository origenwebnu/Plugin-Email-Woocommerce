# WC Custom Emails

Plugin de WordPress para personalizar todas las plantillas HTML de correo de WooCommerce.

## Requisitos

- WordPress 5.8+
- PHP 7.4+
- WooCommerce activo

## Instalación

1. Descarga o clona este repositorio.
2. Copia la carpeta `wc-custom-emails` en `wp-content/plugins/`.
3. Activa el plugin **WC Custom Emails** desde el panel de WordPress.
4. En el menú lateral aparecerá **Emails WC**.

## Uso

1. Ve a **Emails WC** en el menú de WordPress.
2. Verás la lista de todos los correos registrados por WooCommerce.
3. Para cada email puedes:
   - **Subir HTML**: sube un archivo `.html` personalizado.
   - **Descargar estándar**: obtén la plantilla original de WooCommerce como referencia.
   - **Restaurar estándar**: elimina tu plantilla personalizada y vuelve al comportamiento por defecto.

Cuando existe una plantilla personalizada para un email, el plugin la usa automáticamente al enviar ese correo, reemplazando la plantilla estándar de WooCommerce.

## Placeholders

Los archivos HTML pueden incluir variables de WooCommerce como:

- `{site_title}`
- `{order_date}`
- `{order_number}`
- `{order_billing_full_name}`

Cada email muestra sus placeholders disponibles en el panel de administración.

## Almacenamiento

Las plantillas personalizadas se guardan en:

```
wp-content/uploads/wc-custom-emails/
```

Cada email se almacena como `{email_id}.html` (por ejemplo: `customer_completed_order.html`).

## Estructura del plugin

```
wc-custom-emails/
├── wc-custom-emails.php          # Archivo principal
├── includes/
│   ├── class-wcce-admin.php      # Panel de administración
│   ├── class-wcce-email-manager.php  # Override de plantillas
│   └── class-wcce-storage.php    # Almacenamiento de archivos
├── templates/
│   └── admin-page.php            # Vista del panel
└── assets/
    └── css/
        └── admin.css
```

## Licencia

GPL v2 o posterior.
