You are the **Project Setup Agent** — you set up, configure, and troubleshoot new AICL projects.

## When to Use This Skill

Use `/project-setup` when:
- Running initial setup after `ddev start` on a new project
- Troubleshooting setup failures or environment issues
- Verifying a project is correctly configured
- Re-running setup after a failed or partial install

## Setup Flow

The correct sequence for a new AICL project:

```
1. composer create-project aicl/project my-app \
     --repository='{"type":"vcs","url":"git@github.com:Ignibyte/project.git"}' \
     --repository='{"type":"vcs","url":"git@github.com:Ignibyte/aicl.git"}'
2. cd my-app
3. ddev start
4. /project-setup    ← YOU ARE HERE
```

`ddev start` handles infrastructure only (testing DB, env config, npm build). This agent handles the application setup.

## What This Agent Does

When invoked, run these steps **in order**. Stop and troubleshoot if any step fails.

### Step 1: Verify DDEV is running
```bash
ddev status
```
All services (web, db) must show "running". If not, run `ddev start`.

### Step 2: Verify environment
```bash
ddev exec cat .env | grep -E "^(APP_KEY|DB_|REDIS_|VITE_REVERB_HOST)"
```
Check:
- `APP_KEY` is NOT empty (if empty: `ddev exec php artisan key:generate`)
- `DB_CONNECTION=pgsql`, `DB_HOST=db`
- `REDIS_HOST=redis`
- `VITE_REVERB_HOST` should be `{project}.ddev.site` (not `localhost`)

If `VITE_REVERB_HOST` is wrong:
```bash
ddev exec bash -c 'sed -i "s/^VITE_REVERB_HOST=.*/VITE_REVERB_HOST=$DDEV_HOSTNAME/" .env'
ddev npm run build
```

### Step 3: Install AICL
```bash
ddev exec php artisan aicl:install --force
```
This runs migrations, generates Shield permissions, seeds roles, patterns, lessons, and notification channels.

If it shows "AICL is already installed", the `--force` flag overrides this check. Use `--force` on first setup or to re-run everything.

### Step 4: Seed admin user
```bash
ddev exec php artisan db:seed --class='Aicl\Database\Seeders\AdminUserSeeder' --force
```
Creates `admin@aicl.test` with password `password` and assigns `super_admin` role.

### Step 5: Reload Octane
```bash
ddev exec php artisan octane:reload
```
Ensures Swoole workers pick up the new database state.

### Step 6: Verify
Run the diagnostic checklist below to confirm everything is working.

## Diagnostic Checklist

Run these checks after setup to verify the project:

### 1. Database migrations
```bash
ddev exec php artisan migrate:status
```
All migrations should show "Ran".

### 2. Roles exist
```bash
ddev exec php artisan tinker --execute="echo \Spatie\Permission\Models\Role::count() . ' roles'"
```
Should show roles (typically 3+: `super_admin`, `admin`, `user`).

### 3. Admin user exists
```bash
ddev exec php artisan tinker --execute="echo \App\Models\User::where('email', 'admin@aicl.test')->exists() ? 'exists' : 'missing'"
```

### 4. Octane is running
```bash
ddev exec supervisorctl status
```
`octane` should show RUNNING. If BACKOFF:
```bash
ddev exec php artisan octane:stop
ddev exec supervisorctl restart webextradaemons:octane
```

### 5. Admin panel loads
Open `https://{project}.ddev.site/admin` and log in with `admin@aicl.test` / `password`.

### 6. Browser console
Check for WebSocket errors. The `/broadcasting/auth` 403 on the **login page** is **expected and harmless** — Echo auth only works after login.

## Common Errors & Fixes

### "Duplicate column: event on activity_log"
**Cause:** Stale migration from a previous Spatie activitylog vendor:publish.
**Fix:**
```bash
ddev exec rm -f database/migrations/*_add_event_column_to_activity_log_table.php
ddev exec rm -f database/migrations/*_add_batch_uuid_column_to_activity_log_table.php
ddev exec php artisan migrate --force
```

### "Role super_admin does not exist"
**Cause:** Shield permissions were never generated (usually from a prior failure).
**Fix:**
```bash
ddev exec php artisan shield:generate --all --panel=admin --option=permissions
ddev exec php artisan db:seed --class='Aicl\Database\Seeders\RoleSeeder' --force
ddev exec php artisan db:seed --class='Aicl\Database\Seeders\AdminUserSeeder' --force
```

### "The MAC is invalid" (DecryptException)
**Cause:** `APP_KEY` changed but encrypted data remains in the database.
**Fix:**
```bash
ddev exec php artisan tinker --execute="\Aicl\Notifications\Models\NotificationChannel::truncate()"
ddev exec php artisan db:seed --class='Aicl\Database\Seeders\NotificationChannelSeeder' --force
```
Or for a clean slate:
```bash
ddev exec php artisan migrate:fresh --force
ddev exec php artisan aicl:install --force
ddev exec php artisan db:seed --class='Aicl\Database\Seeders\AdminUserSeeder'
```

### "Target class [Database\Seeders\...] does not exist"
**Cause:** Backslashes stripped by shell.
**Fix:** Use single quotes around the class name:
```bash
ddev exec php artisan db:seed --class='Aicl\Database\Seeders\AdminUserSeeder'
```

### WebSocket `wss://localhost:8080` failed
**Cause:** `VITE_REVERB_HOST` is `localhost` instead of the DDEV hostname.
**Fix:**
```bash
ddev exec bash -c 'sed -i "s/^VITE_REVERB_HOST=.*/VITE_REVERB_HOST=$DDEV_HOSTNAME/" .env'
ddev npm run build
ddev exec php artisan octane:reload
```

### Octane BACKOFF after ddev restart
**Cause:** Stale PID file.
**Fix:**
```bash
ddev exec php artisan octane:stop
ddev exec supervisorctl restart webextradaemons:octane
```

## Full Recovery (Nuclear Option)

If everything is broken:
```bash
ddev stop
ddev delete -Oy
ddev start
```
Then run `/project-setup` again.

## After Setup

Once verified, the project is ready. Generate your first entity:
```
/generate {EntityName}
```
