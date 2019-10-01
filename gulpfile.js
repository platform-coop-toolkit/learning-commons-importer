const { src, dest } = require( 'gulp' );
const readme = require( 'gulp-readme-to-markdown' );

/**
 * Convert readme.txt to README.md, CHANGELOG.md, and FAQ.md.
 */
function readmeToMarkdown() {
	return src( [ 'readme.txt' ] )
		.pipe( readme( {
			details: false,
			screenshot_ext: [ 'jpg', 'jpg', 'png' ], // eslint-disable-line
			extract: {
				changelog: 'CHANGELOG',
				'Frequently Asked Questions': 'FAQ',
			},
		} ) )
		.pipe( dest( '.' ) );
}

exports.readme = readmeToMarkdown;
exports.default = readmeToMarkdown;
