# Control-G — Entorno Docker

## Requisitos previos
- [Docker Desktop](https://www.docker.com/products/docker-desktop) instalado y corriendo
- PowerShell (Windows) o Terminal (Mac/Linux)

---

## ⚡ Inicio Rápido (Paso a Paso)

Navega a la carpeta raíz del proyecto (donde está `docker-compose.yml`):

```powershell
cd "c:\Users\Miguel Angel\Downloads\Control-G"
```

### Paso 1: Levantar los contenedores

```powershell
docker compose up -d --build
```
> Espera ~2 minutos la primera vez (descarga imágenes + build PHP).

### Paso 2: Ejecutar migraciones

```powershell
docker compose exec app php artisan migrate:fresh
```

### Paso 3: Ejecutar el Seeder de Datos Iniciales

```powershell
docker compose exec app php artisan db:seed
```

### Paso 4: Ejecutar el Seeder de Demostración SRS (Flujos A, B, C, D)

```powershell
docker compose exec app php artisan db:seed --class=SRSDemonstrationSeeder
```

### Paso 5: Ejecutar la Suite de Pruebas (Objetivo: 100% GREEN)

> ⚠️ **IMPORTANTE:** NO uses `php artisan migrate:fresh --env=testing` dentro del contenedor.
> El servicio `app` define `DB_DATABASE=control_g` como variable de entorno del contenedor, y esas
> variables de *proceso* tienen prioridad sobre `.env.testing`. Ese comando **borraría la BD de desarrollo**.
> Los tests de `php artisan test` son seguros porque `phpunit.xml` sobreescribe
> `DB_DATABASE=control_g_testing` y migran solos la BD de testing (`RefreshDatabase`).

```powershell
# Solo correr los tests (migra automáticamente la BD control_g_testing, no toca control_g)
docker compose exec app php artisan test
```

Si alguna vez necesitas pre-migrar manualmente la BD de testing, pasa la variable explícitamente:

```powershell
docker compose exec -e DB_DATABASE=control_g_testing app php artisan migrate:fresh --force
```

---

## URLs del proyecto

| Servicio      | URL                          |
|---------------|------------------------------|
| Frontend      | http://localhost:5173        |
| API Laravel   | http://localhost:8050/api    |
| Health Check  | http://localhost:8050/up     |
| MySQL (local) | `localhost:3308` (user: `controlguser`, pass: `secret`, db: `control_g`) |

---

## Comandos útiles

```powershell
# Ver logs de todos los contenedores
docker compose logs -f

# Ver logs solo de PHP
docker compose logs -f app

# Acceder al shell del contenedor PHP
docker compose exec app sh

# Detener todos los contenedores
docker compose down

# Detener Y eliminar volúmenes (resetea DB)
docker compose down -v
```

---

## Estructura Docker

```
Control-G/
├── docker-compose.yml         ← Orquestación de servicios
├── docker/
│   ├── nginx/
│   │   └── default.conf       ← Virtual host Nginx → PHP-FPM
│   └── mysql/
│       └── init.sql           ← Crea DBs `control_g` y `control_g_testing`
└── backend/
    ├── Dockerfile             ← PHP 8.3 FPM Alpine con extensiones Laravel
    └── .dockerignore          ← Excluye vendor/, .env, etc. del build
```
