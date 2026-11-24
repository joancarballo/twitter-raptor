# Bot diario de X: #VelociraptorAttack

Script PHP sin dependencias que publica cada día a las 23:59 (hora de Madrid) un mensaje con variables.
Se ejecuta con un cron de SiteGround.

## 1. Crear la app en X

1. Entra en https://developer.x.com con tu cuenta personal y crea un proyecto y una App.
   Revisa el plan de acceso: necesitas uno que permita **publicar** posts (≈30 al mes).
2. En la App: **User authentication settings → Set up**
   - App permissions: **Read and write**
   - Type of App: *Web App, Automated App or Bot*
   - Callback URL / Website: cualquier URL tuya (p. ej. `https://tudominio.com`)
3. En **Keys and tokens**:
   - *API Key and Secret* → `X_API_KEY` y `X_API_SECRET`
   - *Access Token and Secret* → **genéralos DESPUÉS de poner "Read and write"**
     (si los generaste antes, regenéralos: si no, darán error 403 al publicar).
     Comprueba que indique "Created with Read and Write permissions".
     Van en `X_ACCESS_TOKEN` y `X_ACCESS_TOKEN_SECRET`.

## 2. Configurar

- **Claves (datos sensibles)**: copia `.env.example` como `.env` y pega las 4 claves.
  `.env` está en `.gitignore`, así que nunca se sube a GitHub.
- **Resto de opciones** (hora, mensajes, plantillas): en `config.php`, que sí se puede subir.

### Variables disponibles

| Variable | Ejemplo |
|---|---|
| `{dia}` | 23 |
| `{mes}` / `{Mes}` | noviembre / Noviembre |
| `{anio}` | 2026 |
| `{dia_semana}` / `{Dia_semana}` | lunes / Lunes |
| `{fecha}` | 23 de noviembre |
| `{fecha_corta}` | 23/11/2026 |
| `{hora}` | 23:59 |
| `{contador}` | días desde `inicio_anual` (MM-DD); vuelve a 1 cada año |
| `{dia_del_anio}` | 327 |
| `{dias_restantes}` | días que quedan para fin de año |
| `{semana}` | número de semana ISO |
| `{luna}` | fase lunar en emoji 🌔 |
| `{estacion}` | otoño |
| `{cuenta_atras:2026-12-25}` | días hasta esa fecha |
| `{azar:🦖\|🦕\|🦴}` | una opción al azar |

Además:
- `plantillas`: se elige una al azar cada día.
- `especiales`: mensajes para fechas concretas (`'12-24' => '...'`). Incluye los festivos nacionales
  y los de la Comunitat Valenciana de fecha fija. Cada uno puede ser un texto o una lista (se elige al azar).
- `especiales_pascua`: festivos móviles, en días respecto al Domingo de Pascua (se calcula cada año):
  `'-2'` Viernes Santo, `'0'` Domingo de Pascua, `'+1'` Lunes de Pascua.
- Prioridad: fecha fija > festivo móvil > plantillas.
- Los hashtags se escriben tal cual (`#VelociraptorAttack`); X los convierte en enlace solo.

## 3. Subir a SiteGround

1. **Site Tools → Site → File Manager**. Crea una carpeta **fuera de `public_html`**, p. ej.
   `www/tudominio.com/raptor-bot/` y sube `bot.php`, `config.php`, `.env` y `.htaccess`.
2. Elige la hora en `config.php` → `'hora_publicacion' => '23:59'` (formato HH:MM, hora de Madrid).
3. **Site Tools → Devs → Cron Jobs**, crea:
   - Comando: `php /home/customer/www/tudominio.com/raptor-bot/bot.php`
     (copia la ruta exacta que muestra el File Manager / el ejemplo de la pantalla de Cron)
   - Intervalo: **cada hora en el mismo minuto** que `hora_publicacion`. Para `23:59` → `59 * * * *`.
     `php bot.php --cron` te imprime la línea exacta.

   El cron se lanza cada hora, pero el bot solo publica cuando en Madrid es la hora configurada
   (si el cron llega tarde, acepta hasta `margen_minutos` de retraso, por defecto 30).
   Así no importa en qué zona horaria esté el servidor ni el cambio de hora verano/invierno.
   Además guarda la fecha en `last_posted.txt` para no publicar dos veces el mismo día.

   **Cuánto tarda:** el cron arranca en los primeros segundos del minuto y publicar lleva 1-3 s,
   así que el tuit sale unos segundos después de la hora indicada. Si el cron se retrasa y ya es
   el día siguiente (p. ej. las 00:15), se publica igualmente el mensaje del día anterior, siempre
   dentro de `margen_minutos` y sin repetir. El tiempo real de cada publicación queda en `bot.log`.

## 4. Probar

Por SSH (Site Tools → Devs → SSH Keys Manager) o creando temporalmente un cron:

```bash
php bot.php --dry-run                      # muestra el mensaje de hoy, no publica
php bot.php --dry-run --date=2026-12-31    # simula otra fecha
php bot.php --force                        # publica ya (prueba real)
```

Los resultados y errores quedan en `bot.log`.

## Errores típicos

- **401**: claves mal copiadas.
- **403 "oauth1 app permissions"**: el Access Token se generó con permisos de solo lectura → regenéralo.
- **403 "duplicate content"**: X no deja publicar exactamente el mismo texto dos veces; usa variables de fecha.
- **402 / 429**: tu plan de la API no tiene cuota de publicación o la has agotado.
