/**
 * Gulpfile.js - Kompletní build systém s hashováním
 *
 * Hashuje VŠECHNO:
 * - CSS (SCSS → minify → hash)
 * - JS (minify → hash)
 * - Images (copy → hash)
 * - SVG sprite (spojí → hash)
 *
 * Vytvoří manifest.json pro Nette Assets
 *
 * BrowserSync:
 * - Hot reload CSS (bez refresh!)
 * - Full reload JS/PHP
 * - Proxy na localhost:8000
 *
 */

const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const autoprefixer = require('gulp-autoprefixer').default;
const cssnano = require('gulp-cssnano');
const terser = require('gulp-terser');
const svgSprite = require('gulp-svg-sprite');
const rev = require('gulp-rev').default || require('gulp-rev');
const fs = require('fs');
const path = require('path');
const { deleteAsync, deleteSync } = require('del');
const browserSync = require('browser-sync').create();

// ========================================
// Cesty
// ========================================

const paths = {
    styles: {
        src: ['assets/scss/**/*.scss', '!assets/scss/**/_*/**'],
        watch: 'assets/scss/**/*.scss', // watch všechny včetně partials
        dest: 'www/assets/css'
    },
    scripts: {
        src: ['assets/js/**/*.js', '!assets/js/**/_*/**'],
        watch: 'assets/js/**/*.js',
        dest: 'www/assets/js'
    },
    images: {
        src: 'assets/images/**/*.{jpg,jpeg,png,gif,webp,svg}',
        dest: 'www/assets/images'
    },
    icons: {
        src: 'assets/icons/*.svg',
        dest: 'www/assets/images'
    },
    templates: {
        watch: 'app/ui/**/*.latte'
    }
};

// ========================================
// Task: Clean
// ========================================

function clean() {
    return deleteAsync([
        'www/assets/css',
        'www/assets/js',
        'www/assets/images',
        'www/assets/sprite*.svg',
        'www/assets/icons*.svg',
        'www/assets/manifest.json',
        'temp/gulp'
    ]);
}

// ========================================
// Task: Styles (SCSS → CSS → Hash)
// ========================================

function styles() {
    return gulp.src(paths.styles.src)
        // Compile SCSS
        .pipe(sass({
            silenceDeprecations: ['legacy-js-api']
        }).on('error', sass.logError))

        // Autoprefixer
        .pipe(autoprefixer({ cascade: false }))

        // Minify
        .pipe(cssnano())

        // Revision (hash v názvu)
        .pipe(rev())

        // Uložit do www/assets/css
        .pipe(gulp.dest(paths.styles.dest))

        // Vytvořit dočasný manifest
        .pipe(rev.manifest('styles-manifest.json', {
            transformer: {
                stringify: obj => JSON.stringify(
                    Object.fromEntries(Object.entries(obj).map(([k, v]) => ['css/' + k, 'css/' + v])),
                    null, 2
                ),
                parse: JSON.parse
            }
        }))
        .pipe(gulp.dest('temp/gulp'));
}

// ========================================
// Task: Scripts (JS → Minify → Hash)
// ========================================

function scripts() {
    return gulp.src(paths.scripts.src)
        // Minify
        .pipe(terser({
            format: {
                comments: false,
            },
            compress: {
                drop_console: true  // odstraní console.log
            }
        }))

        // Revision (hash v názvu)
        .pipe(rev())

        // Uložit do www/assets/js
        .pipe(gulp.dest(paths.scripts.dest))

        // Vytvořit dočasný manifest
        .pipe(rev.manifest('scripts-manifest.json', {
            transformer: {
                stringify: obj => JSON.stringify(
                    Object.fromEntries(Object.entries(obj).map(([k, v]) => ['js/' + k, 'js/' + v])),
                    null, 2
                ),
                parse: JSON.parse
            }
        }))
        .pipe(gulp.dest('temp/gulp'));
}

// ========================================
// Task: Images (Optimize → Hash)
// ========================================

function images() {
    return gulp.src(paths.images.src, { encoding: false })
        // Revision (hash v názvu)
        .pipe(rev())

        // Uložit do www/assets/images
        .pipe(gulp.dest(paths.images.dest))

        // Vytvořit dočasný manifest
        .pipe(rev.manifest('images-manifest.json', {
            transformer: {
                stringify: obj => JSON.stringify(
                    Object.fromEntries(Object.entries(obj).map(([k, v]) => ['images/' + k, 'images/' + v])),
                    null, 2
                ),
                parse: JSON.parse
            }
        }))
        .pipe(gulp.dest('temp/gulp'));
}

// ========================================
// Task: SVG Sprite (Spojit → Hash)
// ========================================

function icons() {
    return gulp.src(paths.icons.src, { encoding: false })
        // Vytvoř SVG sprite
        .pipe(svgSprite({
            mode: {
                symbol: {
                    dest: '.',
                    sprite: 'icons.svg',
                    example: false
                }
            },
            svg: {
                xmlDeclaration: false,
                doctypeDeclaration: false,
                namespaceIDs: false,
                dimensionAttributes: false
            }
        }))

        // Revision (hash v názvu)
        .pipe(rev())

        // Uložit do www/assets
        .pipe(gulp.dest(paths.icons.dest))

        // Vytvořit dočasný manifest
        .pipe(rev.manifest('icons-manifest.json', {
            transformer: {
                stringify: obj => JSON.stringify(
                    Object.fromEntries(Object.entries(obj).map(([k, v]) => ['images/' + k, 'images/' + v])),
                    null, 2
                ),
                parse: JSON.parse
            }
        }))
        .pipe(gulp.dest('temp/gulp'));
}

// ========================================
// Task: Merge Manifests
// Sloučí všechny dočasné manifesty do jednoho
// ========================================

function mergeManifests(done) {
    const finalManifest = {};
    const tempDir = 'temp/gulp';
    const finalManifestPath = 'www/assets/manifest.json';

    // 1. NEJDŘÍV načti existující manifest (pokud existuje)
    if (fs.existsSync(finalManifestPath)) {
        const existingManifest = JSON.parse(fs.readFileSync(finalManifestPath, 'utf8'));
        Object.assign(finalManifest, existingManifest);
    }

    // 2. PAK přepiš/přidej nové položky z temp manifestů
    if (fs.existsSync(tempDir)) {
        const files = fs.readdirSync(tempDir);

        files.forEach(file => {
            if (file.endsWith('-manifest.json')) {
                const manifestPath = path.join(tempDir, file);
                const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));

                // Přidej/přepiš do finálního manifestu
                Object.assign(finalManifest, manifest);
            }
        });
    }

    // 3. Zapiš finálnní manifest
    fs.writeFileSync(
        finalManifestPath,
        JSON.stringify(finalManifest, null, 2)
    );

    console.log('✅ Manifest aktualizován:', Object.keys(finalManifest).length, 'souborů');

    // 4. Smaž temp složku
    deleteSync([tempDir]);

    done();
}

// ========================================
// Task: BrowserSync Server
// ========================================

function serve(done) {
    browserSync.init({
        proxy: 'http://www.vuk.local', // tvůj PHP server
        port: 3000,               // BrowserSync poběží na :3000
        open: true,               // automaticky otevře prohlížeč
        notify: false,            // vypne notifikace
        ui: false,                // vypne BrowserSync UI

        // Možnosti pro ladění
        // logLevel: 'debug',
        // logPrefix: 'Gulp',
    });
    done();
}

// ========================================
// Task: Reload Browser
// ========================================

function reload(done) {
    browserSync.reload();
    done();
}

// ========================================
// Task: Inject CSS (hot reload bez refresh!)
// ========================================

function injectCSS() {
    return gulp.src(paths.styles.dest + '/*.css')
        .pipe(browserSync.stream());
}

// ========================================
// Task: Watch
// ========================================

function watch() {
    gulp.watch(paths.styles.watch, gulp.series(styles, mergeManifests, reload));
    gulp.watch(paths.scripts.watch, gulp.series(scripts, mergeManifests, reload));
    gulp.watch(paths.images.src, gulp.series(images, mergeManifests, reload));
    gulp.watch(paths.icons.src, gulp.series(icons, mergeManifests, reload));
    gulp.watch(paths.templates.watch, reload);
}

// ========================================
// Task: Build (production)
// ========================================

const build = gulp.series(
    clean,
    gulp.parallel(styles, scripts, images, icons),
    mergeManifests
);

// ========================================
// Task: Dev (development s watchem)
// ========================================

const dev = gulp.series(
    clean,
    gulp.parallel(styles, scripts, images, icons),
    mergeManifests,
    serve,
    watch
);

// ========================================
// Exports
// ========================================

exports.clean = clean;
exports.styles = styles;
exports.scripts = scripts;
exports.images = images;
exports.icons = icons;
exports.mergeManifests = mergeManifests;
exports.serve = serve;
exports.reload = reload;
exports.watch = watch;
exports.build = build;
exports.dev = dev;
exports.default = dev;


/**
 * ========================================
 * Výsledný manifest.json
 * ========================================
 *
 * www/assets/manifest.json:
 * {
 *   "css/style.css": "css/style-a1b2c3d4.css",
 *   "js/app.js": "js/app-e5f6g7h8.js",
 *   "icons.svg": "icons-i9j0k1l2.svg",
 *   "images/logo.png": "images/logo-m3n4o5p6.png",
 *   "images/hero.jpg": "images/hero-q7r8s9t0.jpg"
 * }
 *
 * Struktura www/assets/:
 * css/
 *   style-a1b2c3d4.css
 * js/
 *   app-e5f6g7h8.js
 * images/
 *   logo-m3n4o5p6.png
 *   hero-q7r8s9t0.jpg
 * icons-i9j0k1l2.svg
 * manifest.json
 */


/**
 * ========================================
 * Package.json dependencies
 * ========================================
 *
 * npm install --save-dev \
 *   gulp \
 *   gulp-sass sass \
 *   gulp-autoprefixer \
 *   gulp-cssnano \
 *   gulp-uglify \
 *   gulp-imagemin@7.1.0 \
 *   gulp-svg-sprite \
 *   gulp-rev \
 *   del
 */