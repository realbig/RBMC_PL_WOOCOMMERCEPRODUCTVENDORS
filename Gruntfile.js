'use strict';
module.exports = function (grunt) {

    // load all grunt tasks
    require('matchdep').filterDev('grunt-*').forEach(grunt.loadNpmTasks);

    grunt.initConfig({

        pkg: grunt.file.readJSON('package.json'),

        // Define watch tasks
        watch: {
            options: {
                livereload: true
            },
            sass: {
                files: ['assets/scss/**/*.scss', '!assets/scss/admin/**/*.scss'],
                tasks: ['sass:main', 'autoprefixer:main', 'notify:sass']
            },
            sass_admin: {
                files: ['assets/scss/admin/**/*.scss'],
                tasks: ['sass:admin', 'autoprefixer:admin', 'notify:sass']
            },
            js: {
                files: ['assets/js/source/*.js'],
                tasks: ['uglify', 'notify:js']
            },
            livereload: {
                files: ['**/*.html', '**/*.php', 'assets/images/**/*.{png,jpg,jpeg,gif,webp,svg}', '!**/*ajax*.php']
            }
        },

        // SASS
        sass: {
            options: {
                sourceMap: true
            },
            main: {
                files: {
                    'assets/css/wc-mlm-front.min.css': 'assets/scss/main.scss'
                }
            },
            admin: {
                files: {
                    'assets/css/wc-mlm-admin.min.css': 'assets/scss/admin/admin.scss'
                }
            }
        },

        // Auto prefix our CSS with vendor prefixes
        autoprefixer: {
            options: {
                map: true
            },
            main: {
                src: 'assets/css/wc-mlm-front.min.css'
            },
            admin: {
                src: 'assets/css/wc-mlm-admin.min.css'
            }
        },

        // Uglify and concatenate
        uglify: {
            options: {
                sourceMap: true
            },
            main: {
                files: {
                    'assets/js/wc-mlm.min.js': [
                        'assets/js/source/*.js'
                    ]
                }
            }
        },

        notify: {
            js: {
                options: {
                    title: '<%= pkg.name %>',
                    message: 'JS Complete'
                }
            },
            sass: {
                options: {
                    title: '<%= pkg.name %>',
                    message: 'SASS Complete'
                }
            }
        }

    });

    // Register our main task
    grunt.registerTask('Watch', ['watch']);
};