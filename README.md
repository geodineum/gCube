<p align="center">
  <a href="https://geodineum.com">
    <img src=".github/geodineum-logo.png" alt="Geodineum" width="128">
  </a>
</p>

# gCube

A WordPress **child theme** for Geodineum: a CSS-transformed 3D cube with six
navigable faces, layered on the `gTemplate` parent theme that provides the
operational backbone. gCube is the visual-identity layer - geometry, faces, and
cube-specific styling - and inherits everything else.

Built by **Niels Erik Toren** · WordPress child theme of gTemplate; version in `style.css`

---

## What it is

gCube is a thin child theme. Its parent, `gTemplate`, owns rendering, the REST
API, the CLI, and every service integration; gCube supplies only what makes a
site *a cube*: the six-face geometry, the per-face customizer controls, the
cube's PWA manifest and service worker, and its face-content templates. A site
runs the chain `gCore → gTemplate → gCube`.

It is also the **reference child theme** - the worked example of how to build on
gTemplate. It parameterizes the parent entirely through the parent's public
hooks, adding no operational code of its own.

## Public build surface

gCube is a leaf: it exposes **no public hooks or API of its own** for other code
to build against. What it *does* is implement a few of gTemplate's documented
hooks - content sources, dynamic CSS, and its own customizer sections - which is
exactly the pattern to copy when building a new geometry. The parent hook
contract those follow is
**`gTemplate/FILTER_REGISTRY.md` (the parent repo)**.

The small surface other components *do* rely on - the `wp gcube viewkey` CLI
verb, the `POST /gcube/v1/sync-face-mapping` REST endpoint, and the
`{site_id}:gnode:face_mapping` ValKey key it maintains - is specified in
**[`CONTRACT.md`](CONTRACT.md)**.

**Internal** - everything under `inc/` (bootstrap, rendering sync, customizer,
assets, analytics) is implementation and may change.

## Capabilities

- **Six-face 3D cube** - a CSS-transformed cube whose faces map to WordPress
  content, with a glass-face display mode.
- **Per-face customization** - customizer controls for each face's content,
  colours, and navigation, rendered to live CSS variables.
- **Cube PWA** - a cube-specific web app manifest and service worker, served at
  `/manifest.json` and `/sw.js`.
- **Face-mapping sync** - keeps the `{site_id}:gnode:face_mapping` key in step
  with the site's content, exposed as an authenticated REST endpoint.
- **Usage analytics** - a wp-admin dashboard for cube-face usage.

## Contract

The precise integration surface - the CLI verb, the face-mapping REST endpoint
and its wire format, the customizer setting schema, and the ValKey keys - is in
**[`CONTRACT.md`](CONTRACT.md)**. Agents should prime from
**[`CONTRACT.scn.md`](CONTRACT.scn.md)**.

## Quick start

The Geodineum installer wires `gCore → gTemplate → gCube` into a site and
activates the theme. Verify it, and exercise its one cube-specific CLI surface:

```sh
wp theme list --status=active --path=/var/www/mysite   # gCube active, gtemplate as parent
wp gcube viewkey --path=/var/www/mysite                # the cube-only CLI verb
```

The cube renders at the site root. To build a **different** geometry, copy
gCube: it shows the whole pattern - a child theme that declares itself purely
through gTemplate's public hooks and adds no operational code. gCube's three
registrations, verbatim in shape:

```php
// Add the cube's glass-face content source (inc/rendering/content-sources/glass.php)
add_filter('gtemplate_content_sources', function (array $sources): array {
    $sources['glass'] = /* the glass-face provider */ ;
    return $sources;
});

// Emit the customizer's face colours as dynamic CSS (inc/customizer/css-output.php)
add_filter('gtemplate_dynamic_css', function (string $css): string {
    return $css . '/* cube face variables */';
});

// Register the cube's own customizer sections (inc/customizer/register.php)
add_action('gtemplate_register_customizer_sections', 'gcube_customize_register');
```

The hooks and their expected shapes are in
`gTemplate/FILTER_REGISTRY.md` (the parent repo).

## Limits worth knowing

- **Not standalone.** gCube requires the gTemplate parent and the gCore framework
  beneath it; activated alone it has no operational backbone.
- **Operational CLI lives in the parent.** The cube ships one verb
  (`wp gcube viewkey`); registration, config, and the rest are `wp gtemplate …`.
- **Chapter-2 surfaces stay inert.** gCube inherits gTemplate's translation and
  AI stubs; those render nothing until their Chapter-2 extension is installed.
- **Face-mapping sync requires `manage_options`.** The REST endpoint is
  authenticated; it is not a public write path.

## Collaborate

Contributions are welcome. Open issues and pick up work on the ecosystem board
at [geodineum.com](https://geodineum.com); issues tagged `good-first-issue` are
a good place to start.

- Fork, branch, and open a pull request against `main`.
- Any change to a wire contract must update **both** `CONTRACT.md` and
  `CONTRACT.scn.md` in the same commit.
- A change to a signed extension must be re-signed in the same commit.

## Author & support

Built by **Niels Erik Toren**.

If you want to support the work:

| Currency | Address |
|---|---|
| Bitcoin (BTC) | `bc1qwf78fjgapt2gcts4mwf3gnfkclvqgtlg4gpu4d` |
| Ethereum (ETH) | `0xf38b517Dd2005d93E0BDc1e9807665074c5eC731` / `nierto.eth` |
| Monero (XMR) | `8BPaSoq1pEJH4LgbGNQ92kFJA3oi2frE4igHvdP9Lz2giwhFo2VnNvGT8XABYasjtoVY2Qb3LVHv6CP3qwcJ8UnyRtjWRZ5` |

## Disclaimer

This software is provided **"as is"**, without warranty of any kind, express or
implied. Use of this software is entirely at your own risk. In no event shall the
author or contributors be held liable for any damages arising from the use or
inability to use this software.

## License

Licensed under either of

* [Apache License, Version 2.0](LICENSE-APACHE)
* [MIT License](LICENSE-MIT)

at your option.
