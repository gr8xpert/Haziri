-- The app user creates one database per tenant (hazri_t_{slug}), so it needs global DDL rights (spec §14.4).
GRANT ALL PRIVILEGES ON *.* TO 'hazri'@'%' WITH GRANT OPTION;

-- Separate test database for Pest.
CREATE DATABASE IF NOT EXISTS hazri_central_test;
FLUSH PRIVILEGES;
