-- Drop the plugin system tables (feature removed).
DELETE FROM migrations WHERE scope LIKE 'plugin:%';
DROP TABLE IF EXISTS plugins;
