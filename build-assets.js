const fs = require('node:fs/promises');
const path = require('node:path');
const sharp = require('sharp');
const esbuild = require('esbuild');

const root = __dirname;
const sourceDir = path.join(root, '.original-assets');
const outputDir = path.join(root, 'assets');
const buildDir = path.join(root, '.build');
const heroFiles = new Set(['hero-hall', 'hero-hall-overlay', 'big-hall-primary']);
const dimensions = {};

async function buildImages() {
  for (const name of (await fs.readdir(sourceDir)).sort()) {
    if (!/\.(jpe?g|png)$/i.test(name)) continue;
    const input = path.join(sourceDir, name);
    const output = path.join(outputDir, `${path.parse(name).name}.webp`);
    const metadata = await sharp(input).metadata();
    const maxWidth = heroFiles.has(path.parse(name).name) ? 1920 : 1600;
    const image = sharp(input).rotate().resize({ width: maxWidth, withoutEnlargement: true });
    await image.webp({ quality: 80, effort: 6 }).toFile(output);
    const result = await sharp(output).metadata();
    dimensions[`${path.parse(name).name}.webp`] = { width: result.width, height: result.height };
    console.log(`WebP ${name} -> ${path.basename(output)} (${result.width}x${result.height})`);
  }
  await fs.mkdir(buildDir, { recursive: true });
  await fs.writeFile(path.join(buildDir, 'image-dimensions.json'), JSON.stringify(dimensions, null, 2));
}

async function minifyAssets() {
  const css = await fs.readFile(path.join(root, 'styles.css'));
  const js = await fs.readFile(path.join(root, 'script.js'));
  const [minCss, minJs] = await Promise.all([
    esbuild.transform(css.toString(), { loader: 'css', minify: true }),
    esbuild.transform(js.toString(), { loader: 'js', minify: true, target: 'es2020', legalComments: 'none' }),
  ]);
  await Promise.all([
    fs.writeFile(path.join(root, 'styles.min.css'), minCss.code),
    fs.writeFile(path.join(root, 'script.min.js'), minJs.code),
  ]);
  console.log('Minified styles.css and script.js');
}

(async () => {
  const requested = process.argv[2];
  await fs.mkdir(outputDir, { recursive: true });
  if (!requested || requested === 'images') await buildImages();
  if (!requested || requested === 'minify') await minifyAssets();
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
