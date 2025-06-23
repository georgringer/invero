/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
import RegularEvent from "@typo3/core/event/regular-event.js";
import {MultiRecordSelectionAction} from "@typo3/backend/multi-record-selection-action.js";

class MultiRecordSelectionBulkAction {
  constructor() {
    new RegularEvent("multiRecordSelection:action:editBulk", this.edit).bindTo(document)
  }

  edit(e) {
    // let url =
    let url = top.TYPO3.settings.FormEngine.moduleUrl;
    url = url.replace('/edit?', '/editBulk?');
    e.preventDefault();
    const t = e.detail, o = MultiRecordSelectionAction.getEntityIdentifiers(t);
    if (!o.length) return;
    const n = t.configuration, i = n.tableName || "";
    "" !== i && (window.location.href = n.url + "&edit[" + i + "][" + o.join(",") + "]=edit&columnsOnly[" + i + "]=" + n.columns + "&returnUrl=" + encodeURIComponent(n.returnUrl || ""))
  }
}

export default new MultiRecordSelectionBulkAction;
