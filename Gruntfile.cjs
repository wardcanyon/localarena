module.exports = function(grunt) {
    let phpunit_args = [];
    if (grunt.option('phpunit-filter') !== undefined) {
        phpunit_args = ['--filter="' + grunt.option('phpunit-filter') + '"'];
    }

    // Project configuration.
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        ts: {
            default : {
                tsconfig: './tsconfig.json',
            },
        },
        prettier: {
            options: {
                configFile: './prettierrc.json',
            },
            files: {
                src: [
                    'src/*.php',
                    'src/module/**/*.php',
                    'src/view/**/*.php',
                    'src/ebg/**/*.ts',
                    'src/*.css',
                    'src/css/**/*.css',
                ],
            },
        },
        shell: {
            build: {
                command: 'docker build -t wardcanyon/localarena-testenv:latest --target=testenv .',
                options: {
                    execOptions: {
                        stdio: 'inherit',
                    },
                },
            },
            test: {
                // This runs the LocalArena test suite in a Docker container.
                // Requires the localarena Docker Compose stack to be running (for the database).
                command: [
                    'docker run -i --rm',

                    '--network localarena_default',
                    '-v $PWD/db/password.txt:/run/secrets/db-password:ro',
                    '-v $PWD/src/module:/src/localarena/module:ro',
                    '-v $PWD/src/game/localarenanoop:/src/game/localarenanoop:ro',

                    'wardcanyon/localarena-testenv:latest',
                    'phpunit --configuration /src/localarena/module/test/phpunit.xml',
                ]
                    .concat(phpunit_args)
                    .join(' '),
                options: {
                    execOptions: {
                        stdio: 'inherit',
                    },
                },
            },
        },
    });

    grunt.loadNpmTasks('grunt-ts');
    grunt.loadNpmTasks('grunt-prettier');
    grunt.loadNpmTasks('grunt-shell');

    grunt.registerTask('default', [
        'ts',
    ]);

    grunt.registerTask('fix', [
        'prettier',
    ]);

    grunt.registerTask('build', ['shell:build']);

    grunt.registerTask('test', ['shell:test']);
};
