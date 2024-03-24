module.exports = function(grunt) {
    // Project configuration.
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        ts: {
            default : {
                tsconfig: './tsconfig.json',
            },
        },
    });

    grunt.loadNpmTasks('grunt-ts');

    grunt.registerTask('default', [
        'ts',
    ]);
};
