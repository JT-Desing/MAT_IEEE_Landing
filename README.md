# MAT_IEEE_Landing

Landing page estatica para Mid-Atlantic Tractor en IEEE-IAS ACA Cement Conference 2026.

## Uso local

Abre `index.html` en el navegador.

## Formulario PHP

GitHub Pages no ejecuta PHP. Para que el formulario envie correos, sube estos archivos a un hosting con PHP.

1. Ejecuta `composer install` en el servidor o en el proyecto antes de subirlo.
2. Copia `mail/config.example.php` como `mail/config.php`.
3. Configura el SMTP y los dos correos destino en `mail/config.php`.
4. Verifica que `index.html` y la carpeta `mail/` queden en el mismo nivel.

## Base de datos

El formulario actual solo envia notificaciones por correo. Si necesitas guardar los leads en MySQL/MariaDB, usa `database/schema.sql` para crear la base de datos y la tabla.
