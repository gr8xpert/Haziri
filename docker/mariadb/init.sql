-- The app user creates one database per tenant (hazri_t_{slug}), so it needs global DDL rights (spec §14.4).
GRANT ALL PRIVILEGES ON *.* TO 'hazri'@'%' WITH GRANT OPTION;

-- Read-only user for "Ask Haziri" (spec §14.4). Grants are per tenant view, added by hazri:ai:grant-readonly / provisioning.
CREATE USER IF NOT EXISTS 'hazri_ai'@'%' IDENTIFIED BY 'hazri_ai';

-- Separate test database for Pest.
CREATE DATABASE IF NOT EXISTS hazri_central_test;
FLUSH PRIVILEGES;
