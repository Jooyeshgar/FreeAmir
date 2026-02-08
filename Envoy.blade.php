@setup
    require __DIR__.'/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    try {
    $dotenv->load();
    $dotenv->required(['DEPLOY_SERVER', 'DEPLOY_REPOSITORY', 'DEPLOY_PATH'])->notEmpty();
    } catch ( Exception $e ) {
    echo $e->getMessage();
    exit;
    }

    $php = $_ENV['DEPLOY_PHP_CMD'] ?? 'ea-php82';
    $composer = $_ENV['DEPLOY_COMPOSER_CMD'] ?? 'ea-php82 $(which composer)';
    $php_fpm = $_ENV['DEPLOY_PHP_FPM'] ?? null;
    $server = $_ENV['DEPLOY_SERVER'] ?? null;
    $repo = $_ENV['DEPLOY_REPOSITORY'] ?? null;
    $path = $_ENV['DEPLOY_PATH'] ?? null;
    $healthUrl = $_ENV['DEPLOY_HEALTH_CHECK'] ?? null;
    $env = $_ENV['APP_ENV'] ?? 'dev';

    if ( substr($path, 0, 1) !== '/' ) throw new Exception('Careful - your deployment path does not begin with /');

    $date = ( new DateTime )->format('YmdHis');
    $branch = isset($branch) ? $branch : "main";
    $path = rtrim($path, '/');
    $releases = $path.'/releases';
    $release = $releases.'/'.$date;
@endsetup

@servers(['web' => $server])

@task('init')
    if [ ! -d {{ $path }}/storage ]; then
    cd {{ $path }}
    git clone {{ $repo }} --branch={{ $branch }} --depth=1 -q {{ $release }}
    echo "Repository cloned"
    mv {{ $release }}/storage {{ $path }}/storage
    ln -s {{ $path }}/storage {{ $release }}/storage
    echo "Storage directory set up"
    cp {{ $release }}/.env.example {{ $path }}/.env
    ln -s {{ $path }}/.env {{ $release }}/.env
    echo "Environment file set up"
    rm -rf {{ $release }}
    echo "Deployment path initialised. run 'envoy run deploy'."
    else
    echo "Deployment path already initialised (storage directory exists)!"
    fi
@endtask

@story('deploy')
    deployment_start
    deployment_links
    deployment_composer
    deployment_npm
    deployment_migrate
    deployment_db_seed
    deployment_cache
    deployment_symlink
    deployment_reload
    deployment_finish
    health_check
    deployment_option_cleanup
@endstory

@story('rollback')
    deployment_rollback
    deployment_reload
    health_check
@endstory

@task('installing_apache', ['on' => 'accRoot'])
    if command -v apachectl > /dev/null 2>&1; then
        echo "Apache is already installed."
    else
        echo "Apache is not installed. Installing now..."
        apt update

        apt install -y apache2

        if command -v apachectl > /dev/null 2>&1; then
            echo "Apache has been installed successfully."
        else
            echo "Failed to install Apache."
        fi
    fi
@endtask

@task('installing_php', ['on' => 'accRoot'])
    if command -v php > /dev/null 2>&1; then
        echo "PHP is already installed."
        php -v  # Display the installed PHP version
    else
        echo "PHP is not installed. Installing now..."
        apt update

        apt install -y php composer php-xml php-curl

        if command -v php > /dev/null 2>&1; then
            echo "PHP has been installed successfully."
            php -v  # Display the installed PHP version
        else
            echo "Failed to install PHP."
        fi
    fi
@endtask

@task('installing_docker')
    if ! command -v docker &> /dev/null; then
        echo "Docker is not installed. Installing Docker..."
        apt update
        apt install -y docker.io docker-compose-v2
    else
        echo "Docker is already installed."
    fi
@endtask

@task('deployment_start')
    cd {{ $path }}
    echo "Deployment ({{ $date }}) started"
    git clone {{ $repo }} --branch={{ $branch }} --depth=1 -q {{ $release }}
    echo "Repository cloned"
@endtask

@task('deployment_links')
    cd {{ $path }}
    rm -rf {{ $release }}/storage
    ln -s {{ $path }}/storage {{ $release }}/storage
    echo "Storage directories set up"
    ln -s {{ $path }}/.env {{ $release }}/.env
    echo "Environment file set up"
@endtask

@task('deployment_composer')
    echo "Installing composer dependencies..."
    cd {{ $release }}
    rm -rf {{ $release }}/composer.lock
    {{ $composer }} install --no-interaction --quiet --no-dev --prefer-dist --optimize-autoloader
@endtask

@task('deployment_migrate')
    {{ $php }} {{ $release }}/artisan migrate --no-interaction --force --seed
@endtask

@task('deployment_db_seed')
    {{ $php }} {{ $release }}/artisan db:seed --no-interaction --force
@endtask

@task('deployment_db_demo_seeder')
    {{ $php }} {{ $release }}/artisan db:seed --class=DemoSeeder --no-interaction --force
@endtask

@task('deployment_npm')
    echo "Installing npm dependencies..."
    cd {{ $release }}
    rm -rf {{ $release }}/node_modules
    rm -rf {{ $release }}/package-lock.json
    npm install
    npm run build
@endtask

@task('deployment_cache')
    {{ $php }} {{ $release }}/artisan view:clear --quiet
    {{ $php }} {{ $release }}/artisan cache:clear --quiet
    {{ $php }} {{ $release }}/artisan config:cache --quiet
    echo "Cache cleared"
@endtask

@task('deployment_symlink')
    ln -nfs {{ $release }} {{ $path }}/current
    echo "Deployment [{{ $release }}] symlinked to [{{ $path }}/current]"
@endtask

@task('deployment_reload')
    {{ $php }} {{ $path }}/current/artisan storage:link
@endtask

@task('deployment_finish')
    echo "Deployment ({{ $date }}) finished"
@endtask

@task('deployment_cleanup')
    cd {{ $releases }}
    find . -maxdepth 1 -name "20*" | sort | head -n -4 | xargs rm -Rf
    echo "Cleaned up old deployments"
@endtask

@task('deployment_option_cleanup')
    cd {{ $releases }}
    @if (isset($cleanup) && $cleanup)
        find . -maxdepth 1 -name "20*" | sort | head -n -4 | xargs rm -Rf
        echo "Cleaned up old deployments"
    @endif
@endtask

@task('health_check')
    @if (!empty($healthUrl))
        if [ "$(curl --write-out "%{http_code}\n" --silent --output /dev/null {{ $healthUrl }})" == "200" ]; then
        printf "\033[0;32mHealth check to {{ $healthUrl }} OK\033[0m\n"
        else
        printf "\033[1;31mHealth check to {{ $healthUrl }} FAILED\033[0m\n"
        fi
    @else
        echo "No health check set"
    @endif
@endtask

@task('deployment_rollback')
    cd {{ $releases }}
    ln -nfs {{ $releases }}/$(find . -maxdepth 1 -name "20*" | sort | tail -n 2 | head -n1)
    {{ $path }}/current
    echo "Rolled back to $(find . -maxdepth 1 -name "20*" | sort | tail -n 2 | head -n1)"
@endtask
