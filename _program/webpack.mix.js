let mix = require('laravel-mix')
let tailwindcss = require('tailwindcss')
let path = require('path')
let postcssImport = require('postcss-import')

mix.webpackConfig({
    stats: {
        children: true,
    },
})


var alias = {
    '@': path.join(__dirname, 'src/'),
}

var options = {}

mix.alias(alias)
    .vue({version: 3})
    .options(options)
    .sourceMaps()
    //.extract()

mix.setPublicPath('./../assets/program/')
    .js('src/main.js', 'main.js')
    .postCss('src/index.css', 'index.css', [postcssImport(), tailwindcss('tailwind.config.js'),])
