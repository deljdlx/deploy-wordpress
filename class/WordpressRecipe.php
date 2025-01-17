<?php

namespace Deljdlx\Deploy\Wordpress;

use Deljdlx\Deploy\Recipe;
use Deljdlx\Deploy\Traits\MySql;

class WordpressRecipe extends Recipe
{
    use MySql {
        MySql::DatabaseExists as mysqlDatabaseExists;
    }

    public function initialize()
    {

        parent::initialize();
        return $this;
    }


    public function registerTasks()
    {
        parent::registerTasks();
        $this->registerMysqlTrait();


        $this->setTask('generateConfiguration', function() {
            return $this->generateConfiguration();
        });

        $this->setTask('installRequirements', function() {
            return $this->installRequirements();
        });


        $this->setTask('createBDD', function() {
            $this->createBDD(
                $this->get('DB_HOST'),
                $this->get('DB_USER'),
                $this->get('DB_PASSWORD'),
                $this->get('DB_NAME')
            );
        });

        $this->setTask('databaseExists', function() {
            return $this->databaseExists();
        });


        $this->setTask('dropBDD', function() {
            $this->dropBDD(
                $this->get('DB_HOST'),
                $this->get('DB_USER'),
                $this->get('DB_PASSWORD'),
                $this->get('DB_NAME')
            );
        });


        $this->setTask('dropTables', function() {
            return $this->dropTables(
                $this->get('DB_HOST'),
                $this->get('DB_USER'),
                $this->get('DB_PASSWORD'),
                $this->get('DB_NAME'),
                $this->get('DB_TABLE_PREFIX')
            );
        });

        $this->setTask('installWordpress', function() {
            return $this->installWordpress(
                $this->get('PUBLIC_FILEPATH'),
                $this->get('WP_HOME'),
                $this->get('SITE_NAME'),
                $this->get('BO_USER'),
                $this->get('BO_PASSWORD'),
                $this->get('BO_EMAIL')
            );
        });

        $this->setTask('chmod', function() {
            return $this->chmod();
        });

        $this->setTask('buildHtaccess', function() {
            return $this->buildHtaccess();
        });

        $this->setTask('displayInformations', function() {
            return $this->displayInformations();
        });

        $this->setTask('activatePlugins', function() {
            return $this->activatePlugins();
        });

        $this->setTask('scaffold', function() {
            return $this->scaffold();
        });

        /*
            deployWordpress deploy files and configure wordpress
        */
        $this->setTask('deployWordpress', function() {
            return $this->deployWordpress();
        });
    }



    public function scaffold()
    {

        // $path = $this->ask('Base path', basename(getcwd()));
        $publicPath = $this->ask('Public folder', $this->get('PUBLIC_FOLDER'));
        $this->set('PUBLIC_FOLDER', $publicPath);


        $this->set('WP_HOME', '{{WP_HOME}}');
        $this->echo ('✔️ Site home URL : ' . $this->get('WP_HOME'));


        $wordpressSourceFolder = $this->ask('Wordpress source folder', $this->get('WP_SOURCE_FOLDER'));
        $this->set('WP_SOURCE_FOLDER', $wordpressSourceFolder);
        $this->echo ('✔️ Wordpress source folder : ' .$this->get('WP_SOURCE_FOLDER'));

        $wordpressContentFolder = $this->ask('Wordpress content folder', $this->get('WP_CONTENT_FOLDER'));
        $this->set('WP_CONTENT_FOLDER', $wordpressContentFolder);
        $this->echo ('✔️ Wordpress content folder : ' .$this->get('WP_CONTENT_FOLDER'));


        $databaseHost = $this->ask('Database host ?', $this->get('DB_HOST'));
        $this->set('DB_HOST', $databaseHost);
        $this->echo ('✔️ Database host : ' .$this->get('DB_HOST'));

        $databaseUserName = $this->ask('Database username ?', $this->get('DB_USER'));
        $this->set('DB_USER', $databaseUserName);
        $this->echo ('✔️ Database username : ' .$this->get('DB_USER'));

        $databaseUserPassword = $this->ask('Database password ?', $this->get('DB_PASSWORD'));
        $databaseName = $this->ask('Database name ?', $this->get('DB_NAME'));

        $boUser = $this->ask('Site admin login ?', $this->get('BO_USER'));
        $this->set('BO_USER', $boUser);
        $this->echo ('✔️ admin username : ' .$this->get('BO_USER'));

        $boPassword = $this->ask('Site admin password ?', $this->get('BO_PASSWORD'));
        $this->set('BO_PASSWORD', $boPassword);

        $boEmail = $this->ask('Site admin email ?', $this->get('BO_EMAIL'));
        $this->set('BO_EMAIL', $boEmail);
        $this->echo ('✔️ admin email : ' .$this->get('BO_EMAIL'));


        $this->set('DB_PASSWORD', $databaseUserPassword);
        $this->set('DB_NAME', $databaseName);



        $this->set('PUBLIC_FILEPATH', $this->get('DEPLOY_FILEPATH') . '/' . $this->get('PUBLIC_FOLDER'));


        // STEP scaffold copying template files

        if(!$this->isDir('{{DEPLOY_FILEPATH}}')) {
            $this->mkdir('{{DEPLOY_FILEPATH}}');
        }


        $this->buildConfigurations();

        $this->cd('{{PUBLIC_FILEPATH}}');


        if(!$this->databaseExists()) {
            $this->echo('Create database ' .  $this->get('DB_NAME') . ' as ' . $this->get('DB_USER') . ' user');
            $this->createBDD(
                $this->get('DB_HOST'),
                $this->get('DB_USER'),
                $this->get('DB_PASSWORD'),
                $this->get('DB_NAME')
            );
        }
        else {
            $this->echo('Database ' .  $this->get('DB_NAME') . ' exists');
        }

        $this->deployWordpress();

        $this->echo('Generating gulp watch file into ' . getcwd());
        $this->generateGulpWatch($this->get('WP_HOME'), getcwd());
    }

    protected function buildConfigurations()
    {
        // STEP handling assets
        $this->upload(__DIR__ . '/../assets/wordpress/public/', '{{PUBLIC_FILEPATH}}');

        $this->replaceInFile('{{PUBLIC_FILEPATH}}/composer.json', 'wp-content/plugins/', $this->get('WP_CONTENT_FOLDER') . '/plugins/');
        $this->replaceInFile('{{PUBLIC_FILEPATH}}/composer.json', 'wp-content/themes/', $this->get('WP_CONTENT_FOLDER') . '/themes/');
        $this->replaceInFile('{{PUBLIC_FILEPATH}}/composer.json', '"wordpress-install-dir": "wp"', '"wordpress-install-dir": "' . $this->get('WP_SOURCE_FOLDER') . '"');


        $this->replaceInFile('{{PUBLIC_FILEPATH}}/wp-cli.yml', 'path: wp', 'path: {{WP_SOURCE_FOLDER}}');
        $this->replaceInFile('{{PUBLIC_FILEPATH}}/index.php', '/wp/wp-blog-header.php', '/{{WP_SOURCE_FOLDER}}/wp-blog-header.php');
    }

    public function deployWordpress()
    {
        $this->echo('Create configuration file');
        $this->generateConfiguration();

        $this->echo('Composer install');
        $this->run('composer install', [
            'tty' => true
        ]);

        $this->echo('Install wordpress');
        $this->execute('installWordpress');


        $this->echo('Create .htaccess');
        $this->execute('buildHtaccess');

        $this->echo('Execute chmod');
        $this->execute('chmod');


        // $this->echo('Activate all plugins');
        // $this->execute('activatePlugins');

        $this->execute('displayInformations');
    }



    public function databaseExists()
    {
        return $this->mysqlDatabaseExists(
            $this->get('DB_HOST'),
            $this->get('DB_USER'),
            $this->get('DB_PASSWORD'),
            $this->get('DB_NAME')
        );
    }

    public function cloneTheme($gitUrl)
    {
        $this->cd('{{PUBLIC_FILEPATH}}/wp-content/themes');
        $this->run('git clone ' . $gitUrl, [
            'tty' => true
        ]);

        $pathName = str_replace('.git', '', basename($gitUrl));
        $this->composerInstall('{{PUBLIC_FILEPATH}}/wp-content/plugins/' . $pathName);
    }

    public function clonePlugin($gitUrl)
    {
        $this->cd('{{PUBLIC_FILEPATH}}/' . $this->get('WP_CONTENT_FOLDER') . '/plugins');
        $this->run('git clone ' . $gitUrl, [
            'tty' => true
        ]);

        $pathName = str_replace('.git', '', basename($gitUrl));

        $this->composerInstall('{{PUBLIC_FILEPATH}}/' . $this->get('WP_CONTENT_FOLDER') . '/plugins/' . $pathName);
    }

    public function updatePlugin($pluginPath)
    {
        $this->cd('{{PUBLIC_FILEPATH}}/' . $this->get('WP_CONTENT_FOLDER') . '/plugins/' . $pluginPath);
        $this->run('git pull ', [
            'tty' => true
        ]);
    }


    public function generateConfiguration() {
        $template = "<?php

define( 'WP_USE_THEMES', " . $this->get('WP_USE_THEMES', true) . " );
define( 'WP_ENVIRONMENT_TYPE', '" . $this->get('WP_ENVIRONMENT_TYPE') . "');
define( 'WP_DEBUG', " . $this->get('WP_DEBUG', true) . " );

define( 'DB_NAME', '" . $this->get('DB_NAME') . "' );
define( 'DB_USER', '" . $this->get('DB_USER') . "' );
define( 'DB_PASSWORD', '" . $this->get('DB_PASSWORD') . "' );
define( 'DB_HOST', '" . $this->get('DB_HOST') . "' );
define( 'DB_CHARSET', '" . $this->get('DB_CHARSET') . "' );
define( 'DB_COLLATE', '" . $this->get('DB_COLLATE') . "' );
\$table_prefix = '" . $this->get('DB_TABLE_PREFIX') . "';


define('WP_HOME', rtrim ( '" . $this->get('WP_HOME') . "', '/' ));
define('WP_SITEURL', WP_HOME . '/" . $this->get('WP_SOURCE_FOLDER') . "');
define('WP_CONTENT_DIR', __DIR__ . '/" . $this->get('WP_CONTENT_FOLDER'). "');
define('WP_CONTENT_URL', WP_HOME . '/" . $this->get('WP_CONTENT_FOLDER'). "');


define('JWT_AUTH_SECRET_KEY', '" . $this->get('JWT_AUTH_SECRET_KEY') . "');
define('JWT_AUTH_CORS_ENABLE', " . $this->get('JWT_AUTH_CORS_ENABLE', true) . ");

define('FS_METHOD','" . $this->get('FS_METHOD') . "');

define( 'AUTH_KEY',         '" . $this->get('AUTH_KEY') . "' );
define( 'SECURE_AUTH_KEY',  '" . $this->get('SECURE_AUTH_KEY') . "' );
define( 'LOGGED_IN_KEY',    '" . $this->get('LOGGED_IN_KEY') . "' );
define( 'NONCE_KEY',        '" . $this->get('NONCE_KEY') . "' );
define( 'AUTH_SALT',        '" . $this->get('AUTH_SALT') . "' );
define( 'SECURE_AUTH_SALT', '" . $this->get('SECURE_AUTH_SALT') . "' );
define( 'LOGGED_IN_SALT',   '" . $this->get('LOGGED_IN_SALT') . "' );
define( 'NONCE_SALT',       '" . $this->get('NONCE_SALT') . "' );
";

        $this->write('{{PUBLIC_FILEPATH}}/configuration-current.php', $template);
        return $this;
    }



    public function activatePlugins()
    {
        $this->cd('{{PUBLIC_FILEPATH}}');
        $this->run('composer run activate-plugins');
        return $this;
    }


    public function displayInformations()
    {
        $this->echo('Wordpress installed : ' . $this->get('WP_HOME'));
        $this->echo('Backoffice : ' . rtrim($this->get('WP_HOME'), '/') . '/' . $this->get('WP_SOURCE_FOLDER') . '/wp-admin');
    }

    public function installWordpress($path, $home, $name, $login, $password, $email)
    {
        $this->cd($path);
        $this->run('wp core install --url="' . $home . '" --title="' . $name . '" --admin_user="' . $login . '" --admin_password="' . $password . '" --admin_email="' . $email . '" --skip-email;', [
            'tty' => true
        ]);
    }

    public function chmod()
    {
        $this->cd('{{PUBLIC_FILEPATH}}');

        $this->run('composer run chmod', [
            'tty' => true
        ]);

        $this->run('sudo chmod -R 775 ' . $this->get('WP_CONTENT_FOLDER'), [
            'tty' => true
        ]);
        return $this;
    }

    public function buildHtaccess()
    {
        if(!$this->isFile('{{PUBLIC_FILEPATH}}/.htaccess')) {
            $this->cd('{{PUBLIC_FILEPATH}}');
            $this->run('composer run activate-htaccess');
            $this->run ("echo 'RewriteCond %{HTTP:Authorization} ^(.*)' >> ./.htaccess");
            $this->run ("echo 'RewriteRule ^(.*) - [E=HTTP_AUTHORIZATION:%1]' >> ./.htaccess");
            $this->run ("echo 'SetEnvIf Authorization \"(.*)\" HTTP_AUTHORIZATION=$1' >> ./.htaccess");
        }
        else {
            $this->echo('.htaccess file already exists');
        }

        return $this;
    }


    public function installRequirements()
    {
        if(!$this->isFile('/usr/local/bin/wp')) {
            $this->run('curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x wp-cli.phar && sudo mv wp-cli.phar /usr/local/bin/wp');
        }
        if($this->isFile('/usr/local/bin/composer')) {
            $this->run('cd /tmp && php -r "copy(\'https://getcomposer.org/installer\', \'composer-setup.php\');" && php composer-setup.php --quiet && sudo mv composer.phar /usr/local/bin/composer');
        }
    }

    public function dropTables($host, $user, $password, $database, $tablePrefix)
    {
        $this->run('mysql -h'. $host .' -u' . $user . ' -p' . $password . ' --execute="' .
            'use '.$database.';' .
            'DROP TABLE `' . $tablePrefix . 'term_relationships`;'.
            'DROP TABLE `' . $tablePrefix . 'terms`;'.
            'DROP TABLE `' . $tablePrefix . 'termmeta`;'.
            'DROP TABLE `' . $tablePrefix . 'users`;'.
            'DROP TABLE `' . $tablePrefix . 'usermeta`;'.
            'DROP TABLE `' . $tablePrefix . 'term_taxonomy`;'.
            'DROP TABLE `' . $tablePrefix . 'links`;'.
            'DROP TABLE `' . $tablePrefix . 'comments`;'.
            'DROP TABLE `' . $tablePrefix . 'commentmeta`;'.
            'DROP TABLE `' . $tablePrefix . 'posts`;'.
            'DROP TABLE `' . $tablePrefix . 'postmeta`;'.
            'DROP TABLE `' . $tablePrefix . 'options`;'.
        '"');
    }
}
