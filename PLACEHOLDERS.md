# Placeholders

This file tracks unconfirmed details and optional photo coverage. Public HTML strips internal placeholder markers. Build inputs and original source images are kept separately from the deployed `assets/` folder.

To add a confirmed image, place its high-quality source in `.original-assets/`, add a WebP reference to the page generator, then run `pnpm install`, `python _build.py` and `python _check.py`.

## Missing images

| File | Ratio | Where | Notes |
| --- | --- | --- | --- |
| `guest-room-2.jpg` | 4:3 | Homepage → The Big Hall Package, featured Guest Rooms row | Second angle of a VIP guest room, to sit beside the main `guest-room.jpg`. The row shows the main photo already; only the HTML comment `<!-- placeholder: guest-room-2.jpg | 4:3 | VIP guest room, second angle -->` marks the spot for the second angle. |

## Open questions for the client

- Confirm the details behind the Small Hall dining arrangement before restoring any capacity claim. The unconfirmed “50 at a time” row is hidden from public pages.
- Confirm the published parking terms (number of spaces, restrictions and accessibility), even though a parking photo and a general parking listing are present.

## Already available (do not re-request)

Original JPG/PNG sources are retained in `.original-assets/`; the optimized WebP files are generated in `assets/`.
