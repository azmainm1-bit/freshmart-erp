-- Runs once on first container init (empty data volume). Creates a
-- separate database for the automated test suite so tests never touch
-- the dev database's data.
CREATE DATABASE supermarket_erp_laravel_test OWNER laravel;
