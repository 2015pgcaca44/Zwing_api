@servers(['development' => 'dev_deployer@206.189.143.230', 'test' => 'dev_deployer@206.189.143.230', 'demo' => 'dev_deployer@206.189.143.230', 'staging' => 'prod_deployer@139.59.37.194' , 'production' => 'prod_deployer@139.59.37.194' ])

@setup
    $branch = 'master';
    $repository = 'git@code.gozwing.com:zwing/api/api.git';
    if($env =='development'){
        $branch = 'development-master';
        $app_dir = '/var/www/html/dev.api.gozwing.com/public_html';
    }elseif($env =='test'){
        $branch = 'release';
        $app_dir = '/var/www/html/test.api.gozwing.com/public_html';
    }elseif($env =='demo'){
        $app_dir = '/var/www/html/demo.api.gozwing.com/public_html';
    }elseif($env =='staging'){
        $app_dir = '/var/www/html/staging.api.gozwing.com/public_html';
    }elseif($env=='production'){
        $app_dir = '/var/www/html/api.gozwing.com/public_html';
    }
    $releases_dir = $app_dir.'/'.'releases';
    $release = date('YmdHis');
    $new_release_dir = $releases_dir .'/'. $release;
@endsetup

@story('deploy', ['on'=> 'development'])
    clone_repository
    run_composer
    update_symlinks
@endstory

@story('test_deploy', ['on'=> 'test'])
    clone_repository
    run_composer
    update_symlinks
@endstory

@story('demo_deploy', ['on'=> 'demo'])
    clone_repository
    run_composer
    update_symlinks
@endstory

@story('staging_deploy', ['on'=> 'staging'])
    clone_repository
    run_composer
    update_symlinks
@endstory

@story('production_deploy', ['on'=> 'production'])
    clone_repository
    run_composer
    update_symlinks
@endstory

@task('clone_repository')
    echo 'Cloning repository'
    [ -d {{ $releases_dir }} ] || mkdir {{ $releases_dir }}
    git clone --branch {{ $branch }} --depth 1 {{ $repository }} {{ $new_release_dir }}
    cd {{ $releases_dir }}
    git reset --hard {{ $commit }}
@endtask

@task('run_composer')
    echo "Starting deployment ({{ $release }})"
    cd {{ $new_release_dir }}
    composer install --prefer-dist --no-scripts -q -o
    php artisan clear-compiled
    composer dump-autoload
@endtask

@task('update_symlinks')
    echo "Linking storage directory"
    rm -rf {{ $new_release_dir }}/storage
    ln -nfs {{ $app_dir }}/storage {{ $new_release_dir }}/storage

    echo 'Linking .env file'
    ln -nfs {{ $app_dir }}/.env {{ $new_release_dir }}/.env

    echo 'Linking current release'
    ln -nfs {{ $new_release_dir }} {{ $app_dir }}/current
@endtask