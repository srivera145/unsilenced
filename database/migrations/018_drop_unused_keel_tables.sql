-- Phase 1.1 removed Keel's Stripe billing, file uploads, API tokens and
-- organizations (multi-tenancy). No code reads these tables or columns any
-- more, so they go. Migrations 002, 004, 005, 008 and 010 that created them
-- are left as they were: they have already run everywhere.
--
-- The foreign key comes off first: MySQL will not drop a column a foreign key
-- uses, and organizations cannot be dropped while users still references it.
ALTER TABLE users DROP FOREIGN KEY fk_users_organization;

ALTER TABLE users
    DROP COLUMN organization_id,
    DROP COLUMN role,
    DROP COLUMN is_super_admin,
    DROP COLUMN stripe_customer_id;

ALTER TABLE activity_log
    DROP INDEX idx_org_created,
    DROP COLUMN organization_id;

DROP TABLE IF EXISTS organization_invites;
DROP TABLE IF EXISTS organizations;
DROP TABLE IF EXISTS subscriptions;
DROP TABLE IF EXISTS files;
DROP TABLE IF EXISTS api_tokens;
