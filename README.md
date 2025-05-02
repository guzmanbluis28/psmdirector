# Psmdirector

**Sincroniza automáticamente los clientes de tu tienda PrestaShop con MDirector**.

Este módulo detecta registros y compras de clientes, y envía los datos a la API de MDirector usando autenticación OAuth2, permitiendo segmentar contactos como compradores y no compradores. También sincroniza campos personalizados como ID de PrestaShop, nombre de empresa, y origen de registro.

## 🚀 Funcionalidades

- Envía automáticamente nuevos registros a MDirector como contactos.
- Actualiza el contacto si luego realiza una compra.
- Soporte para campos personalizados:
  - `EsCliente`
  - `IDPrestaShop`
  - `Empresa`
  - `Origen`
- Configuración de credenciales mediante archivo `.env`.

## ⚙️ Requisitos

- PHP 8.0 o superior
- PrestaShop 8.x
- Composer
- Cuenta activa en [MDirector](https://www.mdirector.com)

## 📦 Instalación

1. Clona el repositorio en la carpeta de módulos:

   ```
   git clone https://github.com/guzmanbluis28/psmdirector.git modules/psmdirector
   ```

2. Accede al directorio del módulo y ejecuta:

    ```
    composer install
    ```

3. Crea un archivo .env basado en el .env.example:

     ```
     cp .env.example .env
     ```

4. Ve al BackOffice de PrestaShop y activa el módulo desde la sección “Módulos”.

## Estructura

```
psmdirector/
│
├── src/                            # Código del cliente OAuth2 MDirector
├── vendor/                         # Dependencias PHP (no subir a GitHub)
├── .env.example                    # Ejemplo de configuración
├── composer.json                  # Configuración de dependencias
├── psmdirector.php                # Lógica principal del módulo
├── README.md
└── config_es.xml                  # Configuración del módulo para PrestaShop
```

## Variables de entorno

El archivo .env debe contener:
```
MDIRECTOR_COMPANY_ID=tu_id
MDIRECTOR_API_SECRET=tu_secret
```

## Testing
Haz pruebas creando clientes en tu tienda y finalizando pedidos. Los datos deben reflejarse en tu lista de contactos de MDirector, incluyendo los campos personalizados.
