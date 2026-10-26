// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import * as React from 'react';
import { formatNumber } from 'utils/html';
import { trans } from 'utils/lang';

interface Props {
  count?: number | null;
  // TomYum: a title of its own in place of the translated one.
  title?: string;
  titleKey: string;
}

export default function ProfilePageExtraSectionTitle(props: Props) {
  return (
    <h3 className='title title--page-extra-small'>
      {props.title ?? trans(props.titleKey)}

      {props.count != null &&
        <span className='title__count'>
          {formatNumber(props.count)}
        </span>
      }
    </h3>
  );
}
