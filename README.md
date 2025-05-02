# Psmdirector

**Sincroniza automáticamente los clientes de tu tienda PrestaShop con MDirector**.

Este módulo detecta registros y compras de clientes, y envía los datos a la API de MDirector usando autenticación OAuth2. También sincroniza campos personalizados como EsCliente, IDPrestaShop, Empresa, y Origen.

## 🚀 Funcionalidades

- Envía automáticamente nuevos registros a MDirector como contactos.
- Actualiza el contacto si luego realiza una compra.
- Soporte para campos personalizados:
  - `EsCliente`
  - `IDPrestaShop`
  - `Empresa`
  - `Origen`
- Configuración de credenciales directamente desde el Backoffice de PrestaShop.

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

3. Desde el Backoffice de PrestaShop:
    - Ve a Módulos > Módulos Instalados.
	- Busca MDirector Sync y haz clic en Configurar.
	- Introduce tu Company ID y API Secret de MDirector.

## Estructura del proyecto

```
psmdirector/
│
├── src/                            # Código del cliente OAuth2 de MDirector
├── vendor/                         # Dependencias PHP (no subir a GitHub)
├── composer.json                  # Configuración de dependencias
├── psmdirector.php                # Lógica principal del módulo
├── config_es.xml                  # Configuración del módulo para PrestaShop
├── README.md
└── logo.png                        # Icono del módulo
```

## 🧪 Testing
Haz pruebas registrando clientes o realizando pedidos en tu tienda. Los datos deben reflejarse automáticamente en tu cuenta de MDirector, incluyendo los campos personalizados y actualizaciones si ya existía el contacto.