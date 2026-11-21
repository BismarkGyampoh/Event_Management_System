# EventMS

EventMS is a PHP and PostgreSQL event discovery, ticket reservation, and organizer management application. It uses server-rendered pages, hosted Paystack checkout in test mode, and Resend for asynchronous booking-confirmation email.

## Documentation

- [System documentation](SYSTEM_DOCUMENTATION.md) — architecture, routes, roles, workflows, database model, security, configuration, and current limitations.
- [Deployment guide](DEPLOYMENT.md) — local/hosted database setup, Render deployment, payment webhook, and notification worker operations.
- [PostgreSQL setup and migration SQL](database_setup_postgresql.sql) — schema, constraints, indexes, triggers, and database functions.

## Prerequisites

- PHP 8.4 (or a compatible PHP runtime), Apache, `pdo_pgsql`, and `mbstring`.
- PostgreSQL.
- Environment configuration for the database and administrator login.

Start with the [local development instructions](SYSTEM_DOCUMENTATION.md#8-local-development-and-deployment). Never commit environment secrets or database backups.
