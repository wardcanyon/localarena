module.exports = function(grunt) {
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
                'dest/default_options': [
                    'src/*.php',
                    'src/module/**/*.php',
                    'src/view/**/*.php',
                    'src/ebg/**/*.ts',
                    'src/*.css',
                    'src/css/**/*.css',
                ],
            },
        },
    });

    grunt.loadNpmTasks('grunt-ts');
    grunt.loadNpmTasks('grunt-prettier');

    grunt.registerTask('default', [
        'ts',
    ]);

    grunt.registerTask('fix', [
        'prettier',
    ]);
};
