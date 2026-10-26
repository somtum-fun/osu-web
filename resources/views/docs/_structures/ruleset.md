## Ruleset

Available rulesets:

Name   | Description
------ | ---------------
fruits | osu!catch
mania  | osu!mania
osu    | osu!standard
taiko  | osu!taiko

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

