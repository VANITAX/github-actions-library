<?php
namespace Deployer;
require 'recipe/common.php';        //adds common necessities for the deployment

set('ssh_type', 'native');
set('ssh_multiplexing', true);

if (file_exists('vendor/deployer/recipes/rsync.php')) {
	require 'vendor/deployer/recipes/rsync.php';
} else {
	require getenv('COMPOSER_HOME') . '/vendor/deployer/recipes/recipe/rsync.php';
}

set('shared_files', ['wp-config.php']);
set('shared_dirs', ['wp-content/uploads']);
set('writable_dirs', [
	'wp-content',
	'wp-content/uploads',
]);
inventory('/hosts.yml');

// Add tests and other directory uncessecary for
// production to exclude block.
set('rsync', [
	'exclude'      => [
		'.git',
		'.github',
		'deploy.php',
		'composer.json',
		'composer.lock',
		'.env',
		'.env.example',
		'.gitignore',
		'.gitlab-ci.yml',
		'Gruntfile.js',
		'package.json',
		'README.md',
		'gulpfile.js',
		'.circleci',
		'package-lock.json',
		'package.json',
		'screenshot.png',
		'phpcs.xml'
	],
	'exclude-file' => true,
	'include'      => [],
	'include-file' => false,
	'filter'       => [],
	'filter-file'  => false,
	'filter-perdir'=> false,
	'flags'        => 'rz', // Recursive, with compress
	'options'      => [ 'delete', 'delete-excluded', 'links', 'no-perms', 'no-owner', 'no-group' ],
	'timeout'      => 300,
]);
set('rsync_src', getenv('build_root'));
set('rsync_dest', '{{release_path}}');


/*  custom task defination    */
desc('Download cachetool');
task('cachetool:download', function () {
	run('wget https://raw.githubusercontent.com/gordalina/cachetool/gh-pages/downloads/cachetool-3.0.0.phar -O {{release_path}}/cachetool.phar');
});

/*  custom task defination    */
desc('Reset opcache');
task('opcache:reset', function () {

	$ee_version = run('ee --version');

	if ( false !== strpos( $ee_version, 'EasyEngine v3' ) ) {

		$output = run('php {{release_path}}/cachetool.phar opcache:reset --fcgi=127.0.0.1:9070');

	} elseif ( false !== strpos( $ee_version, 'EE 4' ) ) {

		cd( '{{deploy_path}}' );
		$output = run( 'ee shell --command="php current/cachetool.phar opcache:reset --fcgi=127.0.0.1:9000" --skip-tty' );

	} else {
		echo 'EasyEngine verison >=3.x.x is required.';
		exit(1);
	}

	writeln('<info>' . $output . '</info>');

});

/*
 * Change permissions to 'www-data' for 'current/',
 * so that 'wp-cli' can read/write files.
 */
desc('Correct Permissions');
task('permissions:set', function () {
	$output = run('chown -R www-data:www-data {{deploy_path}}/current && chown www-data:www-data {{deploy_path}}/current/*');
	writeln('<info>' . $output . '</info>');
});

/*   deployment task   */
desc('Deploy the project');
task('deploy', [
	'deploy:prepare',
	'deploy:unlock',
	'deploy:lock',
	'deploy:release',
	'rsync',
	//'cachetool:download',
	'deploy:symlink',
	'permissions:set',
	//'opcache:reset',
	'deploy:unlock',
	'cleanup'
]);
after('deploy', 'success');
