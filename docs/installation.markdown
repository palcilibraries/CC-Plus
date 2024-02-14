---
title: Installation
layout: default
nav_order: 2
---

# CC-PLUS Installation Instructions (KYVL/Catalyst Branch)

CC-Plus is currently designed to run as a standalone web-based Laravel application connected to a MySQL database and a web server.  It allows for multiple, or just a single, consortia to be managed within a host system.  The report harvesting system uses the SUSHI protocol, and expects to receive valid and conformant COUNTER-5 usage reports. The code repository and this documentation can be downloaded at: [http://github.com/palcilibraries/CC-PLUS](http://github.com/palcilibraries/CC-PLUS).

**Be Aware : the KYVL/Catalyst code branch is still under development. Care, feeding, customizing, and bug-fixing may be necessary for the system to work well for your specific environment - *use at your own risk*.**

Once the application is installed, the application administrator will need to configure the membership of the consortia, users who will be using the data along with their roles, and the report providers.  The schedule of data harvesting is also configurable and involves validation and storage in the database.  The raw data can be saved as it is received as JSON (encrypted in the filesystem).  Harvested data stored in the database can then be queried to build, display, and/or download faceted reports.


## Table of Contents
* [Prerequisites](#prerequisites)
* [Installation](#installation)
    + [Step 1: Apache](#step-1-apache)
    + [Step 2: Download the Application](#step-2-download-the-application)
    + [Step 3: Setup the Environment](#step-3-setup-the-environment)
    + [Step 4: Install the application](#step-4-install-the-application)
    + [Step 5: Update the Webserver Directory](#step-5-update-the-webserver-directory)
    + [Step 6: Setup Initial Databases](#step-6-setup-initial-databases)
    + [Step 7: Migrate Initial Database Tables](#step-7-migrate-initial-database-tables)
    + [Step 8: Seed Tables](#step-8-seed-tables)
    + [Step 9: Add a Consortium](#step-9-add-a-consortium)
    + [Step 10: Reset Server Administrator](#step-10-reset-server-administrator)
    + [Step 11: Define Harvesting Schedule (Optional)](#step-11-define-harvesting-schedule-optional)
    + [Step 12: Add Scheduler to System Cron (Optional)](#step-12-add-scheduler-to-system-cron-optional)
* [CC-Plus Artisan Commands](#cc-plus-artisan-commands)
* [License](#license)

## Prerequisites
* Apache 2.4+
	* Make sure `mod_rewrite` is enabled
* MySQL (5.7.9+) or MariaDB (10.3+)
    (Note that MySQL 8.x may have installation issues relating to GRANT commands)
* PHP: 7.3+ including
	* php-gd
	* php-xml
	* php-zip
	* php-mysql
  * php-mbstring
	* libapache2-mod-php
* Node.js and npm
* Composer
* Git

## Installation (Un*x)

### Step 1: Apache
Make sure you have a working [apache server configured](https://httpd.apache.org/docs/2.4/), including `mod_rewrite`, for serving the publicly-accessible elements of the CC-Plus application. For the purposes of these instructions, we will refer to this place as: `/var/www/ccplus/`.

Define the public-facing web directory settings something along the lines of:
```bash
        DocumentRoot "/var/www/ccplus"
		. . . .
        <Directory "/var/www/ccplus">
            Options Indexes FollowSymLinks MultiViews
            AllowOverride All
            Order allow,deny
            allow from all
        </Directory>
```
Enable mod_rewrite for Apache:
```bash
# mkdir /var/www/ccplus
# a2enmod rewrite (Ubuntu, enabling for another O/S will differ)
# service apache2 restart
```
 PHP memory settings need to be generous to support large the harvesting and decoding of large JSON reports. The initial
 recommendation is to set the memory limit to 1024Mb and increase from there, if necessary:
 ```bash
 #!  /etc/php.ini  ,   /etc/php/V.v/apache2/php.ini, or equivalent
    ...
    ; Maximum amount of memory a script may consume (128MB)
    ; http://php.net/memory-limit
    ; memory_limit = 128M
    memory_limit = 1024M

```
 Firewalls, SSL/HTTPS, or other organizational requirements are not addressed in this document.

### Step 2: Download the application
The Laravel application itself, including encryption keys, output, logs, etc. will (should) exist outside the served Apache folder. We will download the repository for the application to `/usr/local` and allow `git` to create the folder: `CC-Plus`.
```bash
$ cd /usr/local
$ git clone -b KYVL_Catalyst_Dev https://github.com/palcilibraries/CC-Plus.git
$ cd CC-Plus
```

### Step 3: Setup the Environment
Next, the local `.env` needs to be modified to match the current host environment. Use your preferred editor to open and modify the .env file:
```bash
$ cp .env.example .env
$ vi .env
```
* Assign APP_URL to the URL that your webserver uses to connect to your public documents folder (step-1, above)
* Assign database credentials (a user with rights to create databases and grant privileges) for BOTH the globaldb and con_template "DB_CONNECTION" groups:
    * DB_USERNAME, DB_PASSWORD, DB_USERNAME2, DB_PASSWORD2
* Update settings for connecting to email and SMTP services (will vary depending on server environment). These settings are necessary for the system to generate forgotten-password reset links.
    * MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION, MAIL_FROM_ADDRESS, MAIL_FROM_NAME

### Step 4: Install the application
First we will setup the application Kernel file:
For simplicity sake, we'll configure our initial installation a single consortium.
You can edit the Kernel.php file to match your operational needs, especially as they relate to [automating report harvesting](#step-10-define-harvesting-schedule-optional). You don't need to define the schedule at this point, but doing so now won't hurt anything.
```bash
$ cd /usr/local/CC-Plus/app/Console
$ cp Kernel.php.example-single ./Kernel.php
$ cd ../..
```
Now run the composer install:
```bash
$ composer install
```
And then install npm:
```bash
$ npm install
```
You also need to generate an encryption key for the application. This key will be used to encrypt the raw JSON report and application data, **not** passwords. This only needs to be done during installation. Resetting this key later will make any existing saved data unrecoverable unless you maintain a record of all previous key value(s). This command will update the `.env` with a unique value for APP_KEY.
```bash
$ php artisan key:generate
   Application key set successfully
```
Next run npm to build the application and the publicly-accessible files for the webserver
```bash
$ npm run prod
```
The webserver will need write access to some folders within the application folder. Assuming the webserver executes with group-membership `www-data` :
```bash
# cd /usr/local/CC-Plus/
# chown -R root:www-data storage
# chmod -R g+rw storage
# chown root:www-data bootstrap/cache
# chmod g+rw bootstrap/cache
```

### Step 5: Update the Webserver Directory
(*Optional*) If you are installing the application to a location other than `/usr/local/CC-Plus`, then you'll need to change the value of the `_CCPHOME_` variable to match your installation path. Modify `public/index.php` to reflect the installation path for the application:
```bash
$ cd /usr/local/CC-Plus/public/
$ mv index.php.example ./index.php
$ vi index.php
   . . . .
	define('_CCPHOME_','/usr/local/CC-Plus/');  // Modify this line as necessary, and include a trailing slash
   . . . .
$ cd ..
```
(**NOT Optional**)
Copy the publicly accessible files to the public webserver folder:
```bash
# cp -r /usr/local/CC-Plus/public/. /var/www/ccplus/
# chown -R root:www-data /var/www/ccplus
```

### Step 6: Setup Initial Databases
Begin this step by creating the two initial CC-Plus databases (using the same user defined in step-3 above):
```bash
$ mysql
mysql> create database ccplus_global;
mysql> create database ccplus_con_template;
mysql> quit
```

### Step 7: Migrate Initial Database Tables
The tables in the ccplus_global database will be shared by all consortia within the host system
```bash
$ cd /usr/local/CC-Plus
$ php artisan migrate:fresh --database=globaldb --path=database/migrations/global
Dropped all tables successfully
Migration table created successfully
Migrating: 2019_07_12_200315_create_datatypes_table
 . . .
Migrated: 2022_09_19_133619_create_global_providers_table (31.52ms)
$
```
The tables in the ccplus_con_template database are used when creating consortia for CC-Plus
```bash
$ php artisan migrate:fresh --database=con_template --path=database/migrations/con_template
Dropped all tables successfully
Migration table created successfully
Migrating: 2019_07_16_111258_create_institutiontypes_table
 . . .
Migrated: 2022_08_03_171565_create_provider_connectors_table (69.44ms)
$
```
### Step 8: Seed Tables
Certain tables in both the global and the template need to be seeded with some initial data.
```bash
$ php artisan db:seed
Seeding: Database\Seeders\ReportsTableSeeder
 . . .
Seeded: Database\Seeders\ConnectionFieldSeeder (5.75ms)
$
```
### Step 9: Add a Consortium
The `ccplus:add_consortium` command script prompts for inputs and creates the new consortium. **Note:** The "database key" is used to create a consortium-specific database named "ccplus_< database-key-value >".
```bash
$ php artisan ccplus:addconsortium
  New consortium name?:
  > MyConsortium

  Primary email for the consortium?:
  > my.email@some.domain.com

  Provide a unique database key for the consortium
   (default creates a random string) []:
  > MyCon1

  Make it active (Y/N) [Y]?:
  > Y

Dropped all tables successfully.
Migration table created successfully.
Migrating .....
  .  .  .  .
Migrated .....
New database migration completed with status: 0
Seeding .....
  .  .  .  .
Seeded: .....
Database seeding completed successfully
Initial database seeding completed with status: 0
Consortium added to global database.
The initial Administrator account for a new consortium is always created with
an email address set to "Administrator".

 Enter a password for this Administrator account?:
 > MyAdminPass

New consortium: MyConsortium Successfully Created.
NOTE: app/Console/Kernel.php needs updating in order to automate harvesting!
$

```

** Congratulations **
You should now be able to connect and login to the application using the Administrator credential for your initial consortium! You can now create users, institutions, and providers through the [web interface](overview.markdown).

### Step 10: Reset Server administrator
The `ccplus:resetadminpw` command script prompts for and resets the credential for the ServerAdmin user. The CC-Plus distribution is seeded with an initial password for this user (ChangeMeNow!), but it really should be changed. The ServerAdmin user is able to create new consortial instances in the system, modify all existing consortia and global CC-Plus settings. Th *resetadminpw* script can be run any time from the system command line to reset this credential.
```bash
$ php artisan ccplus:resetadminpw
 Enter a new password for the 'ServerAdmin' user (required)  []:
 >  some-new-password-string
 Reset all consortia system-wide, including the template [Y]?:
 > Y
ccplus_CON1 Successfully Updated.
ccplus_con_template Successfully Updated.

```
### Step 11: Define Harvesting Schedule (Optional)
Automated harvesting for CC-Plus is defined using the schedule defined in `app/Console/Kernel.php` (which we created in [Step 4, above](#step-4-install-the-application)). The initial file is configured to automate harvesting for a single consortium using two queue handler processes (workers) which are scheduled to run every ten minutes. This means that at least one of the workers will wake and check for recently queued jobs every 10-minutes. An example file for a two-consortium configuration is also included, named: `Kernel.php.example-multiple`.

More details on scheduling tasks in Laravel applications can be found [here: https://laravel.com/docs/8.x/scheduling](https://laravel.com/docs/8.x/scheduling)
### Step 12: Add Scheduler to System Cron (Optional)
The default Kernel.php Scheduler configuration expects to be launched on a regular interval (for example, every 10 minutes). If nothing needs to be processed, the scheduler will exit until the next cycle. These lines (or a close approximation) need to be added to the system cron processing to enable unattended harvesting:
```
# Run CC+ Laravel scheduler every 10 minutes
*/10 * * * * root cd /usr/local/CC-Plus && /usr/bin/php /usr/local/CC-Plus/artisan schedule:run >> /dev/null 2>&1
```
## CC-Plus Artisan Commands
The Laravel environment for CC-Plus includes a set of Console Commands for use at the system-level to manage or operate certain parts of the application. A list of the commands themselves can be displayed via:
```bash
$ cd /usr/local/CC-Plus
$ php artisan | grep ccplus
```
Help for the individual commands is also available, for example:
```bash
$ cd /usr/local/CC-Plus
$ php artisan help ccplus:addconsortium
```
A brief description for each command is below. See the help screen for each command for complete details on arguments and options.
* ccplus:addconsortium  
	Adds a database and administrator credential to a CC-Plus host system
* ccplus:resetadminpw
  Resets the password for ServerAdmin in one or all consortium instances
* ccplus:global-provider-update
  Updates the CC-Plus Global Provider definitions to match settings from the Project COUNTER API
* ccplus:data-archive  
	Exports stored CC-Plus report data, institution/provider configuration, and, optionally, global table data to an importable .SQL file.  
* ccplus:data-purge  
	Removes stored CC-Plus report data from the database.
* ccplus:sushibatch  
	Command-line submission to batch-process the submission of report harvests
* ccplus:sushiloader  
	Intended to run nightly by the Kernel.php scheduler, this command scans the SUSHI settings for all institutions and providers within a consortium and loads requests into the gloabl jobs queue (globaldb:jobs table).
* ccplus:sushiqw  
	Intended to run by the Kernel.php scheduler (by default every 10 minutes), this command scans the jobs queue, issues SUSHI requests to report providers, and stores/logs the results.
* ccplus:C5test  
	This is a command for testing raw report data. Accepts COUNTER-5 JSON report data from a file and attempts to validate and store it in the running system

## License
Apache 2.0 License. See [License File](LICENSE) for more information.
