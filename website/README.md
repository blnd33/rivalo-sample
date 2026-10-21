# RIVALO — Coming-Soon Website

A static launch-preview page. It uses plain HTML, CSS, and JavaScript, with no build step, backend, tracking, or external downloads.

```
website/
├── index.html   page content and SVG graphics
├── style.css    design tokens, layout, and animation
└── script.js    countdown, scroll reveal, and footer year
```

## Open the preview locally
- **Quickest:** double-click `index.html`.
- **Local server (closer to how it runs live):** from this folder, run `py -3 -m http.server 5173` (or `npx serve .`) and open http://localhost:5173.

## Set the launch date
Open `script.js` and edit the line near the top:

```js
const LAUNCH_DATE = null;                         // shows "Launch date coming soon"
const LAUNCH_DATE = "2027-03-15T18:00:00+03:00";  // example format: 6 PM at UTC+3
```

- The date **must** include a timezone offset (`+03:00`, `-05:00`) or a `Z` for UTC. Dates without one are rejected, and the browser console shows a warning.
- Once the date passes, the timer is replaced with "The countdown is complete. Stay tuned for our launch announcement."

## Edit text and colors
- **Text:** all wording is in `index.html`. Each section is marked with a numbered comment (`1. HEADER` … `8. FOOTER`).
- **Colors, fonts, spacing:** edit the tokens under `:root` at the top of `style.css`. For example, `--lime` is the accent and `--bg` is the background.
- **Wordmark:** the `<symbol id="wordmark">` near the top of `index.html`. It's reused in the header, footer, and phone mockup.
- **Email sign-up section:** it is preview-only and disabled. Delete everything between `<!-- SIGNUP START -->` and `<!-- SIGNUP END -->` to remove it. To connect a real service later, remove the `disabled` attributes and point the form at that provider.

## Publish
Upload the three files (`index.html`, `style.css`, `script.js`) as they are to any static host, such as Netlify (drag-and-drop), Vercel, GitHub Pages, Cloudflare Pages, or regular web hosting. Once the real domain is known, add `<link rel="canonical">` and `og:url` tags in `index.html`.
