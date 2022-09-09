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
 *
 */

let mix = require('laravel-mix');
let config = require('./webpack.mix.config');

// https://github.com/postcss/autoprefixer
const postcssAutoprefixer = require("autoprefixer")();

// https://github.com/elchininet/postcss-rtlcss
const postcssRTLCSS = require('postcss-rtlcss')({
    safeBothPrefix: true
});

const dist_dir = 'dist/jc-fancy-treeview';

//https://github.com/gregnb/filemanager-webpack-plugin
const FileManagerPlugin = require('filemanager-webpack-plugin');

// Disable mix-manifest.json (https://github.com/laravel-mix/laravel-mix/issues/580#issuecomment-919102692)
// Prevent the distribution zip file containing an unwanted file
mix.options({ manifest: false })

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
                postcssAutoprefixer
            ]
        });
}
