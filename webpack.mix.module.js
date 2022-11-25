/**
 * Laravel mix - Fancy Treeview
 *
 * Output:
 * 		- dist
 *        - resources
 *          - css
 *          - lang (.mo files)
 *          - views
 *        FancyTreeviewModule.php
 *        module.php
 *        LICENSE.md
 *        README.md
 */

let mix = require('laravel-mix');
let config = require('./webpack.mix.config');

// https://github.com/postcss/autoprefixer
const postcssAutoprefixer = require("autoprefixer")();

// https://github.com/elchininet/postcss-rtlcss
const postcssRTLCSS = require('postcss-rtlcss')({
    safeBothPrefix: true
});

//https://github.com/bezoerb/postcss-image-inliner
const postcssImageInliner = require('postcss-image-inliner')({
    assetPaths: [config.images_dir],
    maxFileSize: 0,
});

if (process.env.NODE_ENV === 'production') {
    mix.styles(config.public_dir + '/css/style.css', config.build_dir + '/style.css')
} else {
    mix
        .setPublicPath('./')
        .sass('src/sass/style.scss', config.public_dir + '/css/style.css')
        .options({
            processCssUrls: false,
            postCss: [
                postcssRTLCSS,
                postcssAutoprefixer,
                postcssImageInliner
            ]
        });
}
