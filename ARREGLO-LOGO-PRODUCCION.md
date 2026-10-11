# Arreglar el logo (y las fotos) que no cargan en producción

## Qué pasa

En `https://barbora.sczsoft.net` el logo se **guarda** bien pero **no se ve** (imagen rota / 404).
El archivo sí está en el servidor (`www/barbora/storage/app/public/companies/1/…`); lo que
falta es la carpeta pública **`public/storage`** que lo expone. Es el paso estándar de Laravel
`php artisan storage:link`, que en este servidor todavía no se corrió.

Esto arregla de una sola vez: el **logo de la empresa**, las **fotos del personal** y cualquier
imagen que se suba en adelante. **No hay nada que desplegar por GitHub ni base de datos.**

---

## Paso 1 — Conectarte por SSH (hosting StackCP)

Tu panel (cp.mipanel.cloud → Acceso SSH) indica:
- **Host:** `ssh.gb.stackcp.com`
- **Usuario:** `sczsoft.net`
- La llave **laptop-e2** ya está cargada, así que entra con tu llave (sin contraseña).

En **PowerShell** o **Windows Terminal** de tu laptop, apuntando a **tu llave de StackCP**
(`stackcp_ed25519`, la que coincide con laptop-e2 del panel):

```
ssh -i C:\Users\Eric\.ssh\stackcp_ed25519 sczsoft.net@ssh.gb.stackcp.com
```

> OJO 1: NO es `barbora.sczsoft.net` (ese dio *timeout*) ni `ssh.gb.stackcp.com` a secas
> (ese entra como usuario «eric»). El usuario va adelante: **`sczsoft.net@ssh.gb.stackcp.com`**.
>
> OJO 2: tienes **dos** llaves en `C:\Users\Eric\.ssh\`: `id_ed25519` (la de GitHub/otros) y
> `stackcp_ed25519` (la de este hosting). Sin el `-i`, SSH ofrece `id_ed25519`, que NO está
> cargada en StackCP → por eso daba *Permission denied (publickey)*. El `-i …\stackcp_ed25519`
> fuerza la correcta.

La primera vez te preguntará *"Are you sure you want to continue connecting?"* → escribe `yes`.

### (Opcional) Para no escribir el `-i` cada vez
Agrega esto al final de `C:\Users\Eric\.ssh\config` (créalo si no existe):

```
Host barbora-prod
    HostName ssh.gb.stackcp.com
    User sczsoft.net
    IdentityFile C:\Users\Eric\.ssh\stackcp_ed25519
```

Y a partir de ahí entras con solo: `ssh barbora-prod`.

---

## Paso 2 — Ir a la carpeta del proyecto

Ya conectado (verás un *prompt* distinto, del servidor):

```
cd www/barbora
ls
```

Debes ver, entre otros, el archivo **`artisan`** y la carpeta **`public`**. Si no aparecen,
busca dónde está el proyecto:

```
cd ~
find . -maxdepth 4 -name artisan 2>/dev/null
```

y haz `cd` a la carpeta que te muestre (la que contiene `artisan`).

---

## Paso 3 — Crear el enlace

```
php artisan storage:link
```

Resultado esperado: *"The [public/storage] link has been connected…"* o *"…created…"*.

**Si `php` no existe** (`command not found`), prueba con la versión instalada, por ejemplo:

```
php8.2 artisan storage:link
```

(o `php8.1`, `php8.3`… Mira cuál hay con `php -v` o `php8.2 -v`.)

**Si dice que el enlace ya existe** pero la imagen sigue rota, rehazlo:

```
php artisan storage:link --force
```

---

## Paso 4 — Verificar

```
ls -la public/storage
```

Debe mostrar algo como:

```
public/storage -> ../storage/app/public
```

Luego, en el navegador:
1. Recarga «Datos de la barbería» con **Ctrl + F5** (recarga forzada, para saltar el 404 que quedó en caché).
2. El logo debe aparecer en la pantalla, en el comprobante de una venta y en el avatar del móvil.
3. (Opcional) Abre directo la URL del logo en una pestaña nueva: ya **no** debe dar 404.

Para salir del servidor: `exit`.

---

## Si después de todo esto el logo SIGUE en 404

Quiere decir que este hosting no sigue *symlinks* para servir archivos. Avísame y lo cambio
por código: servir el logo y las fotos por una ruta de Laravel (sin depender de `public/storage`).
Eso se despliega por GitHub como siempre, sin tocar el servidor.
