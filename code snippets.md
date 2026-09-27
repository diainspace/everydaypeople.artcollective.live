## Not sure when to use
sudo /opt/homebrew/bin/httpd -k start
sudo /opt/homebrew/bin/httpd -k graceful
sudo /opt/homebrew/bin/httpd -k stop


## Maria DB 32-character cookie-encryption secret
9d5d057499794fb385c6bb1e3eefbd74

## Start MariaDB
brew services start mariadb@10.6

## Restart MariaDB
brew services restart mariadb@10.6

## Start fronend
## cd ~/Dropbox/@dev/web/everydaypeople.artcollective.live/frontend
## npm run dev
npm --prefix "$HOME/Dropbox/@dev/web/everydaypeople.artcollective.live/frontend" run dev

## Start Backend

## cd ~/Dropbox/@dev/web/everydaypeople.artcollective.live
## php -S 127.0.0.1:8000 -t backend/public
sudo brew services start httpd
brew services start php@8.3

## /opt/homebrew/etc/httpd/extra/local-sites.conf - Add additional site projects to virtual host
nano /opt/homebrew/etc/httpd/extra/local-sites.conf


## After virtual host changes
/opt/homebrew/bin/httpd -t
## Returns Syntax OK

## Then
## sudo brew services restart httpd //Updated for phpMyAdmin install/use

sudo /opt/homebrew/bin/httpd -k graceful

## Stopping httpd
sudo /opt/homebrew/bin/httpd -k stop

## You do not need to restart Apache after editing PHP, HTML, CSS, JSON, or JavaScript files. Reload it only after changing Apache configuration. MariaDB and PHP-FPM are separate background services:

brew services start mariadb@10.6
brew services start php@8.3

## Those normally start automatically when you sign in (og in to your Mac user account after starting or restarting the computer) because Homebrew registered them as user services. Homebrew registered MariaDB and PHP-FPM as macOS background services. They should start automatically when you log into your Mac as dianardozzi.

brew services list

## Result mariadb@10.6  started php@8.3      started

## main user is local_admin pwd is cuid

## Setting up DB in MariaDB via terminal
sudo /opt/homebrew/opt/mariadb@10.6/bin/mariadb -u root

## At the MariaDB prompt MariaDB [(none)]> , create the database: 
CREATE DATABASE acl_easyblog
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

## Create DB user for Easyblog

CREATE USER 'acl_easyblog_app'@'localhost'
IDENTIFIED BY '$Tn@23eULVvcpg6%ChY$rqNe';

## pwd $Tn@23eULVvcpg6%ChY$rqNe

## Setting permissions
GRANT SELECT, INSERT, UPDATE, DELETE
ON acl_easyblog.*
TO 'acl_easyblog_app'@'localhost';

## Verify both

SHOW DATABASES LIKE 'acl_easyblog';

SHOW GRANTS FOR 'acl_easyblog_app'@'localhost';

## Exit

EXIT;

## Test the application (easyblog) user account and enter password

/opt/homebrew/opt/mariadb@10.6/bin/mariadb \
  -u acl_easyblog_app \
  -p \
  acl_easyblog

## Successful connection should show MariaDB [acl_easyblog]> prompt

EXIT;

## Importing data from JSON commands

cd "$HOME/Dropbox/@dev/web/everydaypeople.artcollective.live"

/opt/homebrew/opt/mariadb@10.6/bin/mariadb \
  -u local_admin \
  -p \
  acl_easyblog \
  < database/migrations/001_initial_schema.sql

## Enter local_admin password cuid and import existing JSON

/opt/homebrew/opt/php@8.3/bin/php tools/import-json-to-mariadb.php

## Accept defaults by entering return, after db user enter password

Database host [localhost]:
Database port [3306]:
Database name [acl_easyblog]:
Database user [acl_easyblog_app]:

## Expected Imported 1 administrator, 1 site record, and 1 post into acl_easyblog.

## Verify through terminal

/opt/homebrew/opt/mariadb@10.6/bin/mariadb \
  -u local_admin \
  -p \
  acl_easyblog \
  -e "SHOW TABLES; SELECT id, title, status, permalink FROM posts;"

## The private database-configuration command is ready. Run:

cd "$HOME/Dropbox/@dev/web/everydaypeople.artcollective.live"

/opt/homebrew/opt/php@8.3/bin/php tools/configure-database.php

## except defaults and enter password 
Database host [localhost]:
Database port [3306]:
Database name [acl_easyblog]:
Database user [acl_easyblog_app]:


## The generated credential file is private, excluded from version control (update .gitignore file), and blocked from browser access. The website still uses JSON, so it will remain operational if this configuration step fails. Once this succeeds, I’ll switch the public pages and admin CMS to MariaDB.
## Update .gitignore /private/config/database.php
## Create a safe example file: backend/private/config/database.example.php





## Direct Admin not accessible fix
## SSH into the VPS as mainops (ssh interserver/enter password for mainops under web development in bitwarden), then run:

sudo systemctl restart directadmin.service

## Then refresh the DirectAdmin login page.
