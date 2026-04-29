# Guía de despliegue en Railway

## Requisitos previos

- Cuenta en [railway.app](https://railway.app) (gratis)
- Repositorio en **GitHub** con el código del proyecto

---

## Paso 1 — Subir el código a GitHub

Si aún no tienes repositorio, créalo en GitHub y sube el proyecto:

```bash
git init
git add .
git commit -m "Preparación para Railway"
git remote add origin https://github.com/TU_USUARIO/TU_REPO.git
git push -u origin main
```

> ⚠️ Asegúrate de que `.env` NO está subido a GitHub (ya está en `.gitignore`).

---

## Paso 2 — Crear el proyecto en Railway

1. Entra en [railway.app](https://railway.app) → **New Project**
2. Selecciona **"Deploy from GitHub repo"**
3. Autoriza Railway y elige tu repositorio
4. Railway detectará el `railway.toml` y usará `docker/php/Dockerfile` automáticamente ✅

---

## Paso 3 — Añadir la base de datos MySQL

1. Dentro del proyecto Railway → pulsa **"+ New"** → **"Database"** → **"MySQL"**
2. Railway crea automáticamente la BD y genera las variables de conexión

---

## Paso 4 — Configurar las variables de entorno en el servicio web

Ve a tu servicio PHP → pestaña **"Variables"** → añade estas variables
(Railway rellena los valores reales de MySQL automáticamente):

| Variable | Valor a poner en Railway |
|---|---|
| `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
| `DB_NAME` | `${{MySQL.MYSQLDATABASE}}` |
| `DB_USER` | `${{MySQL.MYSQLUSER}}` |
| `DB_PASS` | `${{MySQL.MYSQLPASSWORD}}` |

> 💡 La sintaxis `${{MySQL.VARIABLE}}` hace que Railway enlace automáticamente
> el valor del plugin MySQL con tu servicio web. El nombre `MySQL` es el del servicio
> que creaste en el paso 3 (ajústalo si lo renombraste).

---

## Paso 5 — Desplegar

Railway desplegará automáticamente al hacer push a `main`. El `entrypoint.sh` se encarga de:

1. ✅ Ajustar el puerto de Apache al que Railway asigne
2. ✅ Esperar a que MySQL esté listo
3. ✅ Importar el esquema SQL automáticamente la primera vez

---

## Credenciales del panel de administración

```
Email:    admin@peluqueria.es
Password: Admin1234!
```

---

## URLs del proyecto desplegado

| Recurso | URL |
|---|---|
| Web principal | `https://TU-PROYECTO.up.railway.app/Pagina/index.php` |
| Panel admin | `https://TU-PROYECTO.up.railway.app/php/login.php` |

> ℹ️ phpMyAdmin **no se despliega en Railway** (no es necesario en producción).
> Para gestionar la BD usa Railway's **Data** tab o conéctate con DBeaver/HeidiSQL
> usando las credenciales del plugin MySQL.

---

## Desarrollo local (sin cambios)

```bash
docker compose up --build   # Primera vez
docker compose up -d        # Las demás veces
```

Accesos locales:
- Web: http://localhost:8080
- phpMyAdmin: http://localhost:8081
