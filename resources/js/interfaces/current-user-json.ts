// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import UserExtendedJson from 'interfaces/user-extended-json';

type CurrentUserDefaultIncludes =
  'blocks'
  | 'follow_user_mapping'
  | 'friends'
  | 'groups'
  | 'is_admin'
  | 'unread_pm_count'
  | 'user_preferences';

// TomYum: osu_direct is osu!direct for supporters and voters with time left (CurrentUserTransformer).
type CurrentUserJson = UserExtendedJson & Required<Pick<UserExtendedJson, CurrentUserDefaultIncludes>> & { osu_direct?: boolean };

export default CurrentUserJson;
