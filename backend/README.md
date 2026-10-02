# Norocel backend

Laravel 12, Sanctum cookie sessions, exact integer money and MySQL 8.4/InnoDB.
Setup, test commands and environment requirements: [project README](../README.md).
API/backup contract: [API_CONTRACT](../API_CONTRACT.md).
Deployment and migration: [RUNBOOK](../RUNBOOK.md).

No SQLite financial migration/test path. `php artisan test --compact` uses the dedicated local MySQL `norocel_test` database.
