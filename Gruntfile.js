/* jshint node:true */
module.exports = function( grunt ){
	'use strict';

	grunt.initConfig({

		shell: {
			options: {
				stdout: true,
				stderr: true
			},
			generatemos: {
				command: [
					'cd languages',
					'for i in *.po; do msgfmt $i -o ${i%%.*}.mo; done'
				].join( '&&' )
			},
			generatepot: {
				command: [
					'makepot'
				].join( '&&' )
			}
		},

	});

	// Load NPM tasks to be used here
	grunt.loadNpmTasks( 'grunt-shell' );

	// Just an alias for pot file generation
	grunt.registerTask( 'pot', [
		'shell:generatepot',
		'shell:generatemos'
	]);

};
