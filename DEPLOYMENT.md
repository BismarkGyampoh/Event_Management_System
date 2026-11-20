# Deploy with Render and Supabase

This project is a PHP application. Render runs it as a Docker web service, and
Supabase provides the PostgreSQL database. Vercel is not part of this deployment.

## 1. Prepare Supabase

1. Create a Supabase project and save its database password.
2. For a new empty database, run `database_setup_postgresql.sql` from this
   repository once to create the tables, functions, and triggers.
3. If the database was already initialized, run the sections marked
   **Production core migration** and **Feature expansion migration**, in that
   order, before deploying the matching application code. They add unpaid cash
   reservations, login throttling, the ticketing and marketplace-payment schema,
   favorites/waitlists/account tokens, and relevant integrity constraints.
   Review the statements and take a database backup first. Do not rerun the full
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
   - `PAYSTACK_MODE`: set to `test` only; this code deliberately rejects live mode
   - `PAYSTACK_TEST_SECRET_KEY`: Paystack test secret, stored as a secret
   - `PAYSTACK_TEST_WEBHOOK_SECRET`: Paystack test webhook signing secret (`whsec_`), stored as a secret
   - `RESEND_API_KEY`: Resend API key, stored as a secret
   - `RESEND_FROM_EMAIL`: verified sender address configured in Resend
   - `APP_BASE_PATH`: optional app subpath used for generated checkout callbacks
4. Deploy and check the Render logs if the database connection fails.

## 3. Payment notifications

Payment confirmation messages are queued in `NotificationOutbox_007` after a
verified booking. Configure a scheduled task to run
`php scripts/process_notification_outbox.php` every five minutes using the same
database and Resend environment variables. The worker uses row locks and a
stable Resend idempotency key so overlapping runs and retries do not issue
duplicate messages. Keep the worker in test mode until the payment account and
marketplace settlement model have been approved.

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
- Cash reservations remain available. Paystack checkout and webhooks are
  restricted to test mode. Configure a Paystack test account and webhook URL
  `https://<host>/<base-path>/webhooks/paystack.php`; the current timestamped
  signature uses the test webhook signing secret, while the legacy signature
  uses the test API key. Do not set live credentials or accept real money.
- Platform checkout also requires an administrator-set commission and an
  organizer subaccount that Paystack has verified and an administrator has
  reviewed. Subaccount availability, KYC, fee allocation and settlement
  reversal must be confirmed for the merchant account before any production
  rollout. Admin refund requests remain subject to provider and settlement
  reconciliation.
- Set `RESEND_API_KEY` and `RESEND_FROM_EMAIL` for confirmation email delivery.
  Other account verification and recovery mail workflows still require wiring
  and should not be advertised as complete.
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
