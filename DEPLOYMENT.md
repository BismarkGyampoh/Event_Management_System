# Deploy with Render and Supabase

This project is a PHP application. Render runs it as a Docker web service, and
Supabase provides the PostgreSQL database. Vercel is not part of this deployment.

## 1. Prepare Supabase

1. Create a Supabase project and save its database password.
2. For a new empty database, run `database_setup_postgresql.sql` from this
   repository once to create the tables, functions, and triggers.
3. If the database was already initialized, run only the section marked
   **Production core migration** in `database_setup_postgresql.sql` before
   deploying the matching application code. It changes bookings to remain
   unpaid until an organizer confirms in-person cash, and adds a pending refund
   state, creates the login throttling table and integrity constraints, and
   removes credential fields from old audit records. Do not rerun the full
   setup script on a database with data.
4. In the project dashboard, open **Connect** and choose **Session pooler**.
   Use the displayed host and username; do not construct them yourself.

`database_setup_postgresql.sql` is the single setup/migration file for this
Supabase app. It is the only database setup script maintained in this project.

If you already have data in another database, migrate it separately before
pointing the app at this Supabase project. The setup SQL creates the schema; it
does not copy existing application data.

## 2. Deploy to Render

1. Push the project to a Git provider connected to Render, including the
   registration and payment-flow changes.
2. In Render, create a **Blueprint** from that repository and use the included
   `render.yaml`, or create a Docker web service using the root `Dockerfile`.
3. Set the environment values requested by the Blueprint:
   - `DB_HOST`: Supabase session-pooler host
   - `DB_PORT`: the port shown for that pooler (usually `5432`)
   - `DB_NAME`: database name shown by Supabase (usually `postgres`)
   - `DB_USER`: session-pooler username shown by Supabase
   - `DB_PASSWORD`: Supabase database password
   - `DB_SSLMODE`: `require`
   - `APP_ADMIN_USERNAME` and `APP_ADMIN_PASSWORD`: choose new, strong admin
     credentials
   - `APP_TRUST_PROXY`: keep enabled for Render's HTTPS proxy so secure session
     cookies and generated links use HTTPS
4. Deploy and check the Render logs if the database connection fails.

Never commit database or admin credentials. The included `.gitignore` and
`.dockerignore` exclude local environment files and backup exports; confirm
those files are not tracked or uploaded separately.

## Notes before production

- The free Render service may sleep when idle. Its local filesystem is
  ephemeral, so generated backups/CSV exports in `admin/backups` are not durable
  across redeploys or restarts. Store important backups outside the web service
  (or use persistent/object storage) and keep independent Supabase backups.
- PHP sessions use local files by default. A service restart can sign users out;
  use shared session storage before running multiple service instances.
- New bookings use unpaid pay-in-person reservations; organizers must confirm
  receipt before a ticket is valid. Online payment collection, payment gateway
  webhooks, and automated refunds are not implemented; do not advertise online
  checkout. Review existing booking/payment rows before deployment: this
  migration does not reconcile old records that may have been marked paid
  without a real charge.
- Attendee registration is available, but email verification and password
  recovery are deferred until an email provider is configured. For public
  production, configure these before relying on email identity.
- Remove any demo accounts or weak passwords from the Supabase database before
  making the application public.
- Rotate credentials for any accounts that used passwords previously recorded
  in audit logs. The migration removes those fields from current audit rows;
  review any separately exported logs/backups under your retention policy.
- The XAMPP PHP installation must have `pdo_pgsql` enabled for local use. The
  Docker image installs it automatically. Keep `display_errors=Off` in public
  deployments and provide the DB and admin credentials through environment
  settings only.
- Backups use a streaming version 2 format for new exports; restore still
  accepts the earlier version 1 JSON backups. Download a fresh backup and
  verify a restore in a separate staging database before relying on it.
