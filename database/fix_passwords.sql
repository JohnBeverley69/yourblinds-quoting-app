-- Quick fix: reset both seeded test users to a working bcrypt hash for
-- the password "rasmuslerdorf". Paste into phpMyAdmin -> SQL tab and run.
--
-- Hash generated with: password_hash('rasmuslerdorf', PASSWORD_DEFAULT)
-- Verified locally before commit.

UPDATE client_users
   SET password_hash = '$2y$10$MYUrovlB4ScyT1J6UbbxMuBo.kWp9r914ygbw9oLvzZUWAwtCRF9S'
 WHERE username IN ('bristol', 'yorkshire');
