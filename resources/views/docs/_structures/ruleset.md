## Ruleset

Available rulesets:

Name   | Description
------ | ---------------
fruits | osu!catch
mania  | osu!mania
osu    | osu!standard
taiko  | osu!taiko

<a id="ruleset-custom-rulesets"></a>

### Custom rulesets

This server also has **custom rulesets**: osu!lazer rulesets made by the community (from [rulesets.info](https://rulesets.info)) that staff have turned on, such as `sentakki` or `tau`. [Get Custom Rulesets](#get-custom-rulesets) lists them. They play osu! beatmaps (converted), and their leaderboards and rankings go by score: they give no pp.

Wherever an endpoint below says it takes a custom ruleset, pass the ruleset's `slug` in place of a [Ruleset](#ruleset) name:

Endpoint                                                  | With a custom ruleset
--------------------------------------------------------- | ---------------------
[Get User](#get-user) `/users/{user}/{slug}`              | That ruleset's statistics (by ranked score; `pp` is 0) and rank history. Adds `custom_ruleset`.
[Get Own Data](#get-own-data) `/me/{slug}`                | The same, for yourself.
[Get User Scores](#get-user-scores) `?mode={slug}`        | That ruleset's best, firsts or recent plays (best and firsts by score).
[Get Beatmap scores](#get-beatmap-scores) `?mode={slug}`  | The beatmap's leaderboard in that ruleset, by score.
[Get Ranking](#get-ranking) `/rankings/{slug}/score`      | Its players by ranked score. Only `score` (or `performance`, the same list); `country` works.

Each of these also takes `variant`: `vanilla` (the default), `relax` or `autopilot`, for that ruleset's Relax or Autopilot board (where the ruleset has those mods).

A custom ruleset's score has its own `ruleset_id` (10 and up, from the ruleset's `id`) and a `custom_ruleset` field (`{id, slug, name}`; `slug` and `name` are null if staff have since turned the ruleset off). In the [initial score format](#score-initial-version), its `mode` and `mode_int` are `osu` and `0` (the beatmap's), so look at `custom_ruleset` to tell it apart from an osu! play.

<a id="ruleset-relax-and-autopilot"></a>

### Relax and Autopilot

Relax and Autopilot plays have their own leaderboards, rankings and pp on this server, kept by the game server rather than the website. Ask for them with these names in place of a [Ruleset](#ruleset) (they are what osu!lazer sends):

Name     | Board
-------- | ---------------
osurx    | osu! Relax
osuap    | osu! Autopilot
taikorx  | osu!taiko Relax
fruitsrx | osu!catch Relax

Endpoint                                                      | Relax / Autopilot
------------------------------------------------------------- | ---------------------
[Get User](#get-user) `/users/{user}/osurx`                   | That board's statistics.
[Get User Scores](#get-user-scores) `?mode=osurx`             | That board's best, firsts or recent plays (or `?mode=osu&variant=osurx`).
[Get Beatmap scores](#get-beatmap-scores) `?mode=osurx`       | The beatmap's board, by pp (or `?mode=osu&variant=rx`, `variant=ap`).
[Get Ranking](#get-ranking) `/rankings/osu/performance?variant=rx` | Its players (`variant=rx` or `ap`; `performance` or `score`).

<a id="ruleset-their-score-ids"></a>

#### Their score ids

A Relax/Autopilot play isn't one of the website's scores, so it has the game server's id, counted separately: the same number is usually also a different, vanilla score. These endpoints tell them apart:

What you ask for                               | Answer
---------------------------------------------- | ---------------------
`/scores/{id}`                                 | The website's (vanilla) score `id`. Never a Relax/Autopilot play.
`/scores/rx/{id}` (or `/scores/ap/{id}`)       | The Relax/Autopilot play with the game server's id `id`: the number on its page, `/scores/rx/{id}` on the website.
`/scores/{id + 1000000000000}`                 | The same play: this is the `id` the endpoints above give it.

Add `/download` for its replay. A Relax/Autopilot play's [Score](#score) has `type` `tomyum_score`, `id` = the game server's id + 1000000000000, and `legacy_score_id` = the game server's id. One played in osu!lazer keeps its website score id instead (osu!lazer matches its replay by it). Don't strip the offset: a bare number always means a vanilla score.
