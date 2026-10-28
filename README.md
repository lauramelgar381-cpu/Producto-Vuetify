# Sistema de Productos

Aplicación web para gestionar productos con autenticación, verificación en dos pasos (2FA) y control de permisos por rol.

- Backend:API REST en Laravel 13 con Laravel Sanctum (tokens), Spatie Laravel Permission (roles y permisos), Google2FA y documentación Swagger.
- Frontend: Vue 3 + Vite con Vuetify 4, Pinia, Vue Router y TanStack Vue Query.

Funcionalidades

- Registro, inicio y cierre de sesión con tokens de Sanctum.
- Inicio de sesión con Google.
- Permisos por rol: la interfaz oculta lo que el usuario no puede usar y el backend lo bloquea con 403.
- CRUD de productos (crear, listar, editar, eliminar) con búsqueda, orden y paginación en el servidor.
- Resumen de productos en el dashboard y exportación a Excel y PDF.
- Gestión de usuarios (solo con el permiso `gestionar-usuarios`).

Estructura

productos/
├── api-productos/
│   └── backend/        # API REST en Laravel
└── fronted_api2/       # Frontend en Vue 3 + Vuetify


Requisitos

- PHP 8.3 o superior y Composer
- Node.js 20 o superior y npm
- SQLite (por defecto) o MySQL

Instalación

1. Backend

cd api-productos/backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed


Revisa `.env` antes de continuar. Las variables que más importan son:

| Variable | Descripción |
| --- | --- |
| `APP_URL` | URL del backend (por defecto `http://localhost:8000`). |
| `DB_CONNECTION` | `sqlite` por defecto. Para MySQL configura `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`. |
| `GOOGLE_CLIENT_ID` | Client ID de Google para el inicio de sesión con Google. |
| `MAIL_*` | Configuración de correo para los códigos de verificación. |

2. Frontend

cd fronted_api2
npm install
cp .env.example .env

| Variable | Descripción |
| --- | --- |
| `VITE_API_URL` | URL base de la API. Debe apuntar al backend, por ejemplo `http://localhost:8000/api`. |
| `VITE_GOOGLE_CLIENT_ID` | Client ID de Google (mismo valor que en el backend). |

3. Ejecutar en desarrollo

Desde la raíz del proyecto, instala las dependencias del script de arranque y levanta ambos servidores:

```bash
npm install
npm run dev
```

- API: http://localhost:8000
- Frontend: http://localhost:5174

Documentación de la API (Swagger): http://localhost:8000/api/documentation

Roles y permisos

| Rol | Permisos |
| --- | --- |
| `superadmin` | Todos los permisos. |
| `admin` | Gestión de productos y usuarios. |
| `usuario` | Solo `ver-productos`. |

Los permisos de productos son `ver-productos`, `crear-productos`, `editar-productos` y `eliminar-productos`. La gestión de usuarios requiere `gestionar-usuarios`.

API

| Método | Ruta | Acceso |

| POST | `/api/register` | Público |
| POST | `/api/login` | Público |
| POST | `/api/auth/code/request` · `/api/auth/code/verify` | Público |
| POST | `/api/auth/2fa/challenge` · `/api/auth/google` | Público |
| GET | `/api/me` · POST `/api/logout` | Autenticado |
| POST | `/api/password/change` | Autenticado |
| GET | `/api/products` · `/api/products/summary` · `/api/products/{id}` | `ver-productos` |
| POST | `/api/products` | `crear-productos` |
| PUT | `/api/products/{id}` | `editar-productos` |
| DELETE | `/api/products/{id}` | `eliminar-productos` |
| GET | `/api/users` · POST `/api/users` · PATCH `/api/users/{id}/status` · POST `/api/users/{id}/reset-2fa` | `gestionar-usuarios` |
| POST | `/api/2fa/setup` · `/api/2fa/enable` · `/api/2fa/disable` | Autenticado |

Pruebas

```bash
cd api-productos/backend
php artisan test
```

Las pruebas usan SQLite en memoria, así que no modifican la base de datos de desarrollo.

Frontend:

```bash
cd fronted_api2
npm run lint
npm run build
```

## Antes de publicar en producción

- El seeder `RoleAndPermissionSeeder` crea usuarios de prueba (`superadmin` y `admin`) con contraseñas por defecto. cámbialas o elimina ese seeder antes de desplegar.
- Define `APP_ENV=production` y `APP_DEBUG=false` en el `.env` del servidor.
- Nunca subas los archivos `.env` al repositorio; usa siempre los `.env.example` como plantilla.
