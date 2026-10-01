-- Unsilenced has no public accounts. Only users with is_admin = 1 may sign in
-- and reach /admin. Granted from the command line, never through the UI:
--   php database/console.php admin:grant someone@example.org
ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0;
