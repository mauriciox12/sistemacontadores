# 💼 Kontify APP - MVP Web App para Contadores en Venezuela

Sistema Web Responsivo (PWA) de gestión administrativa, fiscal y control de clientes contables con soporte nativo multi-entorno (**XAMPP Local** & **Namecheap Shared Hosting / cPanel**).

---

## 🚀 1. Despliegue en Entorno Local (XAMPP)

1. **Colocar los archivos:**
   Ubica esta carpeta dentro del directorio `htdocs` de XAMPP (por ejemplo: `C:/xampp/htdocs/kontify/`).
2. **Crear la Base de Datos:**
   - Abre phpMyAdmin (`http://localhost/phpmyadmin/`).
   - Importa el archivo [`schema.sql`](file:///c:/Users/mauri/Desktop/Sistemas%20Mau/Sistema%20para%20contadores%20personalizado/schema.sql). Esto creará la base de datos `kontify_app` y las tablas correspondientes con datos de ejemplo.
3. **Verificar `config.php`:**
   - Asegúrate de que `define('ENTORNO', 'local');` esté activo.
4. **Acceder al sistema:**
   - Abre en tu navegador `http://localhost/kontify/index.php`.

---

## ☁️ 2. Migración a Producción (Namecheap / cPanel)

1. **Subir archivos:**
   - Sube todos los archivos a la carpeta `public_html` (o subdominio) mediante el Administrador de Archivos de cPanel o vía FTP.
2. **Crear Base de Datos en cPanel:**
   - Ve a **MySQL Database Wizard** en cPanel y crea una base de datos y un usuario con todos los privilegios.
   - Entra a **phpMyAdmin** de cPanel e importa [`schema.sql`](file:///c:/Users/mauri/Desktop/Sistemas%20Mau/Sistema%20para%20contadores%20personalizado/schema.sql).
3. **Activar Entorno de Producción en `config.php`:**
   - Cambia la línea de entorno:
     ```php
     define('ENTORNO', 'produccion');
     ```
   - Actualiza las credenciales de cPanel en el bloque `produccion`:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'tuusuario_kontify');
     define('DB_USER', 'tuusuario_kuser');
     define('DB_PASS', 'TuClaveSegura#2026');
     ```
4. **Permisos de Archivos:**
   - El sistema crea y verifica automáticamente las carpetas en `uploads/clientes/` con permisos `0755` para que no haya errores de subida.

---

## 🛠️ Estructura del Proyecto

- [`config.php`](file:///c:/Users/mauri/Desktop/Sistemas%20Mau/Sistema%20para%20contadores%20personalizado/config.php): Switch multi-entorno, detección dinámica de URLs `BASE_URL`, validación de RIF venezolano, cálculo de vencimiento SENIAT y enlace WhatsApp.
- [`db.php`](file:///c:/Users/mauri/Desktop/Sistemas%20Mau/Sistema%20para%20contadores%20personalizado/db.php): Conexión PDO con manejo de errores adaptativo.
- [`index.php`](file:///c:/Users/mauri/Desktop/Sistemas%20Mau/Sistema%20para%20contadores%20personalizado/index.php): **Módulo 1** - Dashboard, KPIs, Buscador en tiempo real, Semáforo fiscal, Calendario SENIAT por terminal de RIF y Botón de Cobro por WhatsApp.
- [`cotizador.php`](file:///c:/Users/mauri/Desktop/Sistemas%20Mau/Sistema%20para%20contadores%20personalizado/cotizador.php): **Módulo 2** - Cotizador contable con cálculo automático USD/BCV, generación de PDF membretado vía `html2pdf.js` y envío estructurado por WhatsApp.
- [`boveda.php`](file:///c:/Users/mauri/Desktop/Sistemas%20Mau/Sistema%20para%20contadores%20personalizado/boveda.php): **Módulo 3** - Bóveda documental y Escáner Móvil (`capture="environment"`) con visor y filtrado mensual.
- [`subir_documento.php`](file:///c:/Users/mauri/Desktop/Sistemas%20Mau/Sistema%20para%20contadores%20personalizado/subir_documento.php): Endpoint seguro para recepción y almacenamiento de imágenes y PDFs.
- [`schema.sql`](file:///c:/Users/mauri/Desktop/Sistemas%20Mau/Sistema%20para%20contadores%20personalizado/schema.sql): Estructura relacional de base de datos MySQL.
- [`manifest.json`](file:///c:/Users/mauri/Desktop/Sistemas%20Mau/Sistema%20para%20contadores%20personalizado/manifest.json) & [`sw.js`](file:///c:/Users/mauri/Desktop/Sistemas%20Mau/Sistema%20para%20contadores%20personalizado/sw.js): Configuración PWA para instalar la app en teléfonos móviles.
