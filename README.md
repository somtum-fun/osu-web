# osu-web (somtum.fun)

The website of [somtum.fun](https://osu.blueskychan.dev): player profiles, rankings, beatmaps, the
store, the staff panels and more. It's a fork of ppy's [osu-web](https://github.com/ppy/osu-web),
changed to run next to [TomYum](https://github.com/somtum-fun/TomYum-OSS), our osu!stable and
osu!lazer server, on one account system.

> somtum.fun is not affiliated with ppy Pty Ltd. osu! is a trademark of ppy Pty Ltd.

## Technologies

- PHP 8 / Laravel, TypeScript, React and Less
- MySQL 8.4
- Redis (cache, sessions and queues, shared with TomYum)
- Elasticsearch (beatmap, user, forum and wiki search)
- Docker Compose, with Caddy in front

## Core features

Everything osu-web has (profiles, rankings, beatmap pages and search, forums, the wiki, news, chat,
comments, the store, multiplayer and daily challenge history), plus:

* One account with TomYum: sign-up and login work for osu!stable, osu!lazer and the website
* Relax and Autopilot: their own leaderboards, rankings and top plays, for stable and lazer
* Beatmap leaderboards sorted by pp or by score, with Vanilla, Relax and Autopilot boards
* Custom beatmaps uploaded to the server, next to osu!'s, with a filter for them
* Ranked mods set by staff: a lazer play with an unranked mod gets no pp
* Project Loved without rounds: requests, reviews, the team's decisions and a blacklist
* Featured artists: imported from osu! or added in the artist editor, with tracks for uploaded sets
* The download page: TumThai (our lazer client), the Somtum Patcher for osu!stable and the
  patched legacy clients (2008 to 2015), read from the file host's `manifest.json`
* Store payments by TrueMoney and Stripe (through
  [payments-service](https://github.com/somtum-fun/payments-service-tomyum))
* Discord and osu! account links on the account settings page
* Favourite game modes on the profile, and second titles under a player's name

## Staff panels

* **Admin panel:** players (restrict, silence, notes, devices, connected profiles), the staff log,
  reports, tickets from an IMAP mailbox, badges, titles, groups, seasonal backgrounds, menu banners,
  tournament banners, the assets host, ranked mods, Ranked Play pools, the daily challenge, pp
  reworks, which TumThai builds may submit scores, and site settings
* **Branding:** the server's name, logo, favicons, videos, download links, vote page and legal pages,
  all set in the admin panel, so another server can run this site under its own name
* **BN panel:** nominating and ranking uploaded sets, with history and featured artist tracks
* **GMT, TC and Project Loved panels:** moderation, tournaments and Loved for their teams

## Additional features

* Charts on the admin overview: players in game, scores and sign-ups per day
* A changelog made from the repositories' history (with Web and Wiki streams)
* Sign-up checks: proxies, Tor exits and hosting networks (proxycheck.io)
* Maintenance mode and a development banner, switched from the admin panel
* Easter eggs

## Running it

The website runs as part of the TomYum stack: TomYum's `deploy/production` Docker Compose file builds
this repository (`docker compose --profile build build osu-web-build`) and runs it on the same MySQL
and Redis as the game server. TomYum's docs explain the whole stack, and the database they share.

For working on the website alone, ppy's [setup guide](SETUP.md) still applies: clone this repository
instead of ppy's, copy `.env.example` to `.env`, and set at least `APP_*` and `DB_*`.

### Settings

The fork's settings are in [`config/tomyum.php`](config/tomyum.php), each with its environment
variable, for example:

| Variable | What it does |
|---|---|
| `TOMYUM_SERVER_NAME`, `BRAND_NAME` | The server's name (the admin panel's Branding page can change it later) |
| `TOMYUM_ADMIN_URL` | TomYum's admin API, used by the staff panels |
| `TOMYUM_ASSETS_URL` | The assets host (avatars, badges, banners) |
| `TOMYUM_PAYMENTS_URL`, `TOMYUM_STORE_ENABLED` | The payments service and the store |
| `TICKETS_*` | The support mailbox read into tickets |
| `MAIL_*` | Outgoing email (SMTP) |
| `DISCORD_OAUTH_*`, `OSU_LINK_*` | Discord and osu! account links |
| `PROXYCHECK_*` | Sign-up proxy checks |

Never commit a filled-in `.env`: it holds secrets.

## Contributing

- **Fork** the project
- Create your **feature branch**: `git checkout -b feature/AmazingFeature`
- Commit your **changes**: `git commit -m 'Add some AmazingFeature'`
- Push **to the branch**: `git push origin feature/AmazingFeature`
- Open a **pull request**

Changes of ours in ppy's files are marked with a `TomYum:` comment, so merging ppy's updates stays
easy. Please do the same.

Our team believes in using AI as an assistant, always under human review. Low-effort AI-generated
("slop") pull requests or comments may be closed without notice.

## License

osu-web is licensed under the [GNU Affero General Public License v3.0](LICENCE), and so is this fork.
If you run a changed copy as a public website, the AGPL asks you to offer its source to your
players too.

The osu! name, logo and artwork belong to ppy Pty Ltd and aren't covered by the licence. Replace them
with your own (Admin panel > Branding) when you run this for your own server.

## Acknowledgments

- [ppy](https://github.com/ppy) for [osu-web](https://github.com/ppy/osu-web) and
  [osu!](https://github.com/ppy/osu)
- [sundeiii](https://github.com/sundeiii) and blueskychan-dev for the fork
- [catboy.best](https://catboy.best) for beatmap mirrors and audio previews
